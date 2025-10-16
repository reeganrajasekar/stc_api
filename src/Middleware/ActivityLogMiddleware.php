<?php

namespace Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use PDO;

class ActivityLogMiddleware
{
    protected $db;
    protected $loggedRoutes;

    public function __construct(PDO $db, array $loggedRoutes = [])
    {
        $this->db = $db;
        $this->loggedRoutes = $loggedRoutes;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);
        
        // Only log if user is authenticated and route should be logged
        $token = $request->getAttribute('token');
        if ($token && $this->shouldLogRoute($request)) {
            $this->logActivity($request, $response, $token);
        }

        return $response;
    }

    protected function shouldLogRoute(Request $request): bool
    {
        $uri = $request->getUri()->getPath();
        $method = $request->getMethod();
        
        // If no specific routes configured, log all authenticated routes
        if (empty($this->loggedRoutes)) {
            return true;
        }
        
        // Check if current route should be logged
        foreach ($this->loggedRoutes as $route) {
            if (isset($route['path']) && isset($route['method'])) {
                if (fnmatch($route['path'], $uri) && strtoupper($route['method']) === strtoupper($method)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    protected function logActivity(Request $request, Response $response, object $token): void
    {
        try {
            $uri = $request->getUri()->getPath();
            $method = $request->getMethod();
            $statusCode = $response->getStatusCode();
            
            // Skip logging if response indicates error (optional)
            if ($statusCode >= 400) {
                return;
            }

            // Determine activity type based on HTTP method and URI
            $activityType = $this->determineActivityType($method, $uri);
            
            // Get client IP
            $ipAddress = $this->getClientIp($request);
            
            // Get device info from User-Agent
            $deviceInfo = $request->getHeaderLine('User-Agent');
            
            // Create activity description
            $activityDescription = $this->createActivityDescription($method, $uri, $statusCode);

            $sql = "INSERT INTO user_activity_log 
                        (user_id, activity_type, activity_description, ip_address, device_info) 
                    VALUES 
                        (:user_id, :activity_type, :activity_description, :ip_address, :device_info)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', (int)$token->id, PDO::PARAM_INT);
            $stmt->bindValue(':activity_type', $activityType, PDO::PARAM_STR);
            $stmt->bindValue(':activity_description', $activityDescription, PDO::PARAM_STR);
            $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
            $stmt->bindValue(':device_info', $deviceInfo, PDO::PARAM_STR);
            $stmt->execute();
            
        } catch (\Throwable $e) {
            // Log error but don't break the request flow
            error_log("ActivityLogMiddleware error: " . $e->getMessage());
        }
    }

    protected function determineActivityType(string $method, string $uri): string
    {
        // Map common endpoints to activity types
        $activityMap = [
            'POST /v1/auth/login' => 'login',
            'POST /v1/auth/mobile-verification' => 'mobile_verification',
            'POST /v1/auth/email-verification' => 'email_verification',
            'POST /v1/auth/profile-creation' => 'profile_creation',
            'POST /v1/auth/refresh' => 'token_refresh',
            'POST /v1/user/change-password' => 'password_change',
            'PUT /v1/user' => 'profile_update',
            'GET /v1/user' => 'profile_view',
            'DELETE /v1/user' => 'account_deletion',
        ];

        $routeKey = $method . ' ' . $uri;
        
        // Check for exact match first
        if (isset($activityMap[$routeKey])) {
            return $activityMap[$routeKey];
        }
        
        // Check for pattern matches
        foreach ($activityMap as $pattern => $activityType) {
            if (fnmatch($pattern, $routeKey)) {
                return $activityType;
            }
        }
        
        // Default activity type based on HTTP method
        switch (strtoupper($method)) {
            case 'GET':
                return 'view';
            case 'POST':
                return 'create';
            case 'PUT':
            case 'PATCH':
                return 'update';
            case 'DELETE':
                return 'delete';
            default:
                return 'api_access';
        }
    }

    protected function createActivityDescription(string $method, string $uri, int $statusCode): string
    {
        return sprintf(
            "%s %s - Status: %d",
            strtoupper($method),
            $uri,
            $statusCode
        );
    }

    protected function getClientIp(Request $request): string
    {
        // Check for IP in various headers (for load balancers, proxies, etc.)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR even if it's private/reserved
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}