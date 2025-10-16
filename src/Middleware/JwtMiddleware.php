<?php

namespace Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Slim\Psr7\Response as SlimResponse;

class JwtMiddleware implements MiddlewareInterface
{

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!preg_match('/^Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(['error' => 'Token not provided']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        try {
            $token = JWT::decode($matches[1], new Key($_ENV['JWT_SECRET'], 'HS256'));
            $request = $request->withAttribute('token', $token);
            return $handler->handle($request);
        } catch (\Exception $e) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(['error' => 'Invalid token']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
    }
}
