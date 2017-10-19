<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$envFile = __DIR__ . '/../.env';

if (file_exists($envFile)) {
    $dotEnv = new Dotenv();
    $values = $dotEnv->parse(file_get_contents($envFile));
    unset($values['APP_ENV']);
    $dotEnv->populate($values);
}

/*
 * temporarily disable the public service deprecations.
 * FIXME!
 */
putenv('SYMFONY_DEPRECATIONS_HELPER=weak');
