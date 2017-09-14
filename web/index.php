<?php

use Symfony\Component\Debug\Debug;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require __DIR__.'/../vendor/autoload.php';

// The check is to ensure we don't use .env in production
(new Dotenv())->load(__DIR__.'/../.env');

if ($_SERVER['APP_DEBUG'] ?? true) {
    // WARNING: You should setup permissions the proper way!
    // REMOVE the following PHP line and read
    // https://symfony.com/doc/current/book/installation.html#checking-symfony-application-configuration-and-setup
    umask(0000);

    Debug::enable();
}

// Request::setTrustedProxies(['0.0.0.0/0'], Request::HEADER_FORWARDED);

$kernel = new AppKernel($_SERVER['APP_ENV'] ?? 'dev', $_SERVER['APP_DEBUG'] ?? false);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
