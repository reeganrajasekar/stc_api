<?php

namespace Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

class RoleMiddleware implements MiddlewareInterface
{
    private $allowed;

    public function __construct(array $roles)
    {
        $this->allowed = $roles;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $token = $request->getAttribute('token');
        if (!isset($token->role) || !in_array($token->role, $this->allowed)) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(['error' => 'Forbidden']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        return $handler->handle($request);
    }
}
