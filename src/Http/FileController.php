<?php

declare(strict_types=1);

namespace App\Http;

use App\Config\AppConfig;
use App\Config\FileMode;
use App\Config\ManagedFile;
use App\Config\TranslationKeys as TK;
use App\Files\FileAccessException;
use App\Files\FileConflictException;
use App\Files\FileStore;
use App\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;

/**
 * Pages to list the configured files and to edit or append to one of them.
 */
final class FileController
{
    public function __construct(
        private readonly Twig $view,
        private readonly AppConfig $config,
        private readonly FileStore $store,
        private readonly Logger $logger,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'files/index.twig', ['files' => $this->config->files]);
    }

    /** @param array<string, string> $args */
    public function show(Request $request, Response $response, array $args): Response
    {
        $file = $this->findFile($request, $args);
        $saved = ($request->getQueryParams()['saved'] ?? null) === '1';

        return $this->renderForm($request, $response, $file, [
            'messageKey' => $saved ? $file->mode->savedMessageKey() : null,
        ]);
    }

    /** @param array<string, string> $args */
    public function save(Request $request, Response $response, array $args): Response
    {
        $file = $this->findFile($request, $args);
        $payload = FileSavePayload::fromPostArray($request->getParsedBody(), $file->mode);
        if (!$payload->isValid()) {
            return $this->renderForm($request, $response->withStatus(422), $file, [
                'text' => $payload->text,
                'hash' => $payload->expectedHash,
                'errorKey' => $payload->errorKey,
            ]);
        }

        try {
            if ($file->mode === FileMode::Edit) {
                $this->store->overwrite($file, $payload->text, $payload->expectedHash);
            } else {
                $this->store->append($file, $payload->text);
            }
        } catch (FileConflictException $e) {
            $this->logger->warning('Save refused, file changed on disk', ['file' => $file->id]);
            return $this->renderForm($request, $response->withStatus(409), $file, [
                'text' => $payload->text,
                'hash' => $e->currentHash,
                'errorKey' => TK::ERROR_CONFLICT,
            ]);
        } catch (FileAccessException $e) {
            $this->logger->error('Save failed', ['file' => $file->id, 'exception' => $e]);
            return $this->renderForm($request, $response->withStatus(500), $file, [
                'text' => $payload->text,
                'errorKey' => TK::ERROR_FILE_ACCESS,
            ]);
        }

        $this->logger->info('File saved', [
            'file' => $file->id,
            'mode' => $file->mode->value,
            'bytes' => strlen($payload->text),
        ]);
        $url = RouteContext::fromRequest($request)->getRouteParser()
            ->urlFor('files.show', ['id' => $file->id], ['saved' => '1']);
        return $response->withStatus(302)->withHeader('Location', $url);
    }

    /** @param array<string, string> $args */
    private function findFile(Request $request, array $args): ManagedFile
    {
        return $this->config->findFile($args['id'] ?? '') ?? throw new HttpNotFoundException($request);
    }

    /**
     * Render the edit or append form. Without 'text' in $vars, an edit form loads the file content;
     * an append form never shows existing content.
     *
     * @param array<string, mixed> $vars
     */
    private function renderForm(Request $request, Response $response, ManagedFile $file, array $vars): Response
    {
        if ($file->mode === FileMode::Edit && !array_key_exists('text', $vars)) {
            try {
                $content = $this->store->read($file);
                $vars += ['text' => $content, 'hash' => $this->store->hash($content)];
            } catch (FileAccessException $e) {
                $this->logger->error('Read failed', ['file' => $file->id, 'exception' => $e]);
                $response = $response->withStatus(500);
                $vars += ['readFailed' => true, 'errorKey' => TK::ERROR_FILE_ACCESS];
            }
        }

        return $this->view->render($response, 'files/show.twig', $vars + [
            'file' => $file,
            'text' => '',
            'hash' => '',
            'readFailed' => false,
            'errorKey' => null,
            'messageKey' => null,
            'csrf' => [
                'name' => $request->getAttribute('csrf_name'),
                'value' => $request->getAttribute('csrf_value'),
            ],
        ]);
    }
}
