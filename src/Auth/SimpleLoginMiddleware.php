<?php

declare(strict_types=1);

namespace App\Auth;

use BenjaminKobjolke\SimpleLogin\SimpleLogin;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Protects every route with php-simple-login. When not logged in, the library renders its
 * login page (and handles the login POST and `?logout`) and ends the request itself.
 */
final class SimpleLoginMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        SimpleLogin::requireAuth();
        return $handler->handle($request);
    }
}
