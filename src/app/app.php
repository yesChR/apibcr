<?php

use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$container = new Container();

AppFactory::setContainer($container);

$app = AppFactory::create();

$app->addRoutingMiddleware();


$app->add(new Tuupola\Middleware\JwtAuthentication([
    "secure" => false,
    "path" => [],
    "ignore" => ["/.*"], // Ignorar todas las rutas
    "secret" => ["acme" => $_ENV['KEY']],
    "algorithm" => ["acme" => "HS256"]
]));


require 'config.php';
require 'conexion.php';
require_once 'routes.php';

$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$app->run();
