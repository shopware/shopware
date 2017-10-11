<?php declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->load(__DIR__.'/../../.env');

require 'WriteGenerator.php';

$writeGenerator = new WriteGenerator();
$writeGenerator->generateAll();