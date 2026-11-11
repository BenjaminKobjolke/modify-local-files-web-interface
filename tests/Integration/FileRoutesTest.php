<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Bootstrap\AppFactory;
use App\Config\AppConfig;
use App\Logger;
use App\Tests\TempDir;
use Monolog\Level;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Request-to-render tests against the real Slim app (routes, CSRF, Twig, file I/O).
 * Authentication is added by public/index.php only, so these run as a logged-in user.
 */
final class FileRoutesTest extends TestCase
{
    private const BASE = '/app';

    private TempDir $dir;
    private string $editPath;
    private string $appendPath;
    /** @var App<ContainerInterface|null> */
    private App $app;

    protected function setUp(): void
    {
        $this->dir = new TempDir();
        $this->editPath = $this->dir->file('edit.txt', "example.com\n");
        $this->appendPath = $this->dir->file('append.txt', "secret line\n");

        $config = AppConfig::fromArray([
            'debug' => true,
            'base_path' => self::BASE,
            'login_password' => 'test',
            'session_lifetime_seconds' => 3600,
            'log_level' => 'debug',
            'files' => [
                'blacklist' => ['label' => 'Blacklist', 'path' => $this->editPath, 'mode' => 'edit'],
                'inbox' => ['label' => 'Inbox', 'path' => $this->appendPath, 'mode' => 'append'],
            ],
        ]);
        $logger = Logger::create('php://memory', Level::Debug);
        $this->app = AppFactory::create($config, dirname(__DIR__, 2), $logger, new \ArrayIterator());
    }

    protected function tearDown(): void
    {
        $this->dir->remove();
    }

    private function get(string $path): ResponseInterface
    {
        return $this->app->handle((new ServerRequestFactory())->createServerRequest('GET', self::BASE . $path));
    }

    /** @param array<string, string> $body */
    private function post(string $path, array $body): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', self::BASE . $path)
            ->withParsedBody($body);
        return $this->app->handle($request);
    }

    /** @return array<string, string> CSRF fields taken from the rendered form */
    private function csrfFieldsFrom(ResponseInterface $response): array
    {
        preg_match_all('/name="(csrf_name|csrf_value)" value="([^"]+)"/', (string) $response->getBody(), $m);
        $this->assertCount(2, $m[1], 'form must carry both CSRF fields');
        return array_combine($m[1], array_map('html_entity_decode', $m[2]));
    }

    private function hashFrom(ResponseInterface $response): string
    {
        $this->assertSame(1, preg_match('/name="hash" value="([0-9a-f]{40})"/', (string) $response->getBody(), $m));
        return $m[1];
    }

    public function testIndexListsConfiguredFiles(): void
    {
        $response = $this->get('/');

        $this->assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $this->assertStringContainsString('Blacklist', $body);
        $this->assertStringContainsString('Inbox', $body);
        $this->assertStringContainsString(self::BASE . '/files/blacklist', $body);
    }

    public function testEditFileShowsContent(): void
    {
        $response = $this->get('/files/blacklist');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString("example.com\n</textarea>", (string) $response->getBody());
    }

    public function testAppendFileHidesContent(): void
    {
        $response = $this->get('/files/inbox');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('secret line', (string) $response->getBody());
    }

    public function testUnknownFileIs404(): void
    {
        $this->assertSame(404, $this->get('/files/nope')->getStatusCode());
    }

    public function testSaveEditFileOverwritesAndRedirects(): void
    {
        $form = $this->get('/files/blacklist');

        $response = $this->post('/files/blacklist', [
            'text' => "a.com\r\nb.com\r\n",
            'hash' => $this->hashFrom($form),
        ] + $this->csrfFieldsFrom($form));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(self::BASE . '/files/blacklist?saved=1', $response->getHeaderLine('Location'));
        $this->assertSame("a.com\nb.com\n", file_get_contents($this->editPath));
        $this->assertStringContainsString('Saved', (string) $this->get('/files/blacklist?saved=1')->getBody());
    }

    public function testSaveWithStaleHashKeepsUserTextAndFile(): void
    {
        $form = $this->get('/files/blacklist');
        file_put_contents($this->editPath, "changed.com\n");

        $response = $this->post('/files/blacklist', [
            'text' => "mine.com\n",
            'hash' => $this->hashFrom($form),
        ] + $this->csrfFieldsFrom($form));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertStringContainsString('mine.com', (string) $response->getBody());
        $this->assertSame("changed.com\n", file_get_contents($this->editPath));
    }

    public function testAppendAddsTextAtEnd(): void
    {
        $form = $this->get('/files/inbox');

        $response = $this->post('/files/inbox', ['text' => 'new line'] + $this->csrfFieldsFrom($form));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame("secret line\nnew line\n", file_get_contents($this->appendPath));
    }

    public function testInvalidAppendShowsErrorAndKeepsFile(): void
    {
        $form = $this->get('/files/inbox');

        $response = $this->post('/files/inbox', ['text' => '   '] + $this->csrfFieldsFrom($form));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame("secret line\n", file_get_contents($this->appendPath));
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $response = $this->post('/files/inbox', ['text' => 'new line']);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame("secret line\n", file_get_contents($this->appendPath));
    }
}
