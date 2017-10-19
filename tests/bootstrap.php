<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotEnv = new Dotenv();
$values = $dotEnv->parse(file_get_contents(__DIR__ . '/../.env'));
unset($values['APP_ENV']);
$dotEnv->populate($values);
