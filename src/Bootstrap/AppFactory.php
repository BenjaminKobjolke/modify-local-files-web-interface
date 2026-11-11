<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Config\AppConfig;
use App\Config\TranslationKeys;
use App\Files\FileStore;
use App\Http\FileController;
use App\Logger;
use PhpLocalization\Localization;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Csrf\Guard;
use Slim\Factory\AppFactory as SlimAppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Twig\TwigFunction;

/**
 * Builds the Slim app: Twig, CSRF protection, routes and error handling.
 * Authentication is added by public/index.php, because php-simple-login needs a real session.
 */
final class AppFactory
{
    /**
     * @param \ArrayIterator<string, string> $csrfStorage CSRF tokens; kept in the session in production
     * @return App<ContainerInterface|null>
     */
    public static function create(
        AppConfig $config,
        string $projectRoot,
        Logger $logger,
        \ArrayIterator $csrfStorage,
    ): App {
        $app = SlimAppFactory::create();
        $app->setBasePath($config->basePath);

        $twig = Twig::create($projectRoot . '/templates', [
            'cache' => false,
            'debug' => $config->debug,
            'strict_variables' => $config->debug,
        ]);
        $localization = new Localization([
            'driver' => 'json',
            'langDir' => $projectRoot . '/lang/',
            'defaultLang' => 'en',
            'fallBackLang' => 'en',
        ]);
        $twig->getEnvironment()->addFunction(new TwigFunction(
            't',
            static fn (string $key, array $params = []): string => (string) $localization->lang($key, $params),
        ));
        $twig->getEnvironment()->addGlobal('TK', new TranslationKeys());

        $controller = new FileController($twig, $config, new FileStore(), $logger);
        $app->get('/', $controller->index(...))->setName('files.index');
        $app->get('/files/{id}', $controller->show(...))->setName('files.show');
        $app->post('/files/{id}', $controller->save(...))->setName('files.save');

        // Slim runs middleware last-added-first: routing, then CSRF, then Twig, then errors outermost.
        $app->addRoutingMiddleware();
        $app->add(new Guard($app->getResponseFactory(), 'csrf', $csrfStorage));
        $app->add(TwigMiddleware::create($app, $twig));
        $app->addErrorMiddleware($config->debug, true, true, $logger);

        return $app;
    }
}
