<?php
use Psr\Container\ContainerInterface;

return function(ContainerInterface $c) {
    $c->set('settings', [
        'db' => [
            'host' => $_ENV['DB_HOST'],
            'name' => $_ENV['DB_NAME'],
            'user' => $_ENV['DB_USER'],
            'pass' => $_ENV['DB_PASS'],
        ],
        // You can use 'APP_ENV' if you want the env string in your app
        'env' => $_ENV['APP_ENV'] ?? 'local',
    ]);
};
