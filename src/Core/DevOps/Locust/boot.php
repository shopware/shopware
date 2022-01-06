<?php

use Shopware\Core\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Dotenv\Dotenv;

set_time_limit(0);

$classLoader = require __DIR__ . '/../../../../vendor/autoload.php';

if (!class_exists(Application::class)) {
    throw new \RuntimeException('You need to add "symfony/framework-bundle" as a Composer dependency.');
}

if (!class_exists(Dotenv::class)) {
    throw new \RuntimeException('APP_ENV environment variable is not defined. You need to define environment variables for configuration or add "symfony/dotenv" as a Composer dependency to load variables from a .env file.');
}

(new Dotenv())->load(__DIR__ . '/../../../../.env');

$input = new ArgvInput();
$env = 'dev';
$debug = (bool) ($_SERVER['APP_DEBUG'] ?? ('prod' !== $env)) && !$input->hasParameterOption('--no-debug', true);

umask(0000);

return Kernel::getConnection();
