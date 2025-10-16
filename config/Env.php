<?php
use Dotenv\Dotenv;

$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';

switch ($serverName) {
    case 'api.thevsafe.com':
        $env = 'prod';
        break;
    case 'dt-api.vsafers.in':
        $env = 'dt';
        break;
    case 'dev-api.vsafers.in':
        $env = 'dev';
        break;
    default:
        $env = 'local';
        break;
}

$envFile = '.env.' . $env;

// Load the .env file from the env directory
$dotenv = Dotenv::createImmutable(__DIR__ . '/../', $envFile);
$dotenv->load();

// Optional: set a global ENV key if needed
$_ENV['APP_ENV'] = $env;
