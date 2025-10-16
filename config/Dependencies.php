<?php
use Psr\Container\ContainerInterface;

return function(ContainerInterface $c) {
    $settings = $c->get('settings');

    // PDO instance
    $c->set(PDO::class, function() use ($settings) {
        $dsn = "mysql:host={$settings['db']['host']};dbname={$settings['db']['name']};charset=utf8";
        return new PDO($dsn, $settings['db']['user'], $settings['db']['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    });
};