<?php

$php = ['8.2'];
$db = ['mysql:8.0'];

$nightly = $_SERVER['argv'][1] ?? false;

if ($nightly) {
    $php = ['8.2', '8.4'];
    $db = ['mysql:8.0', 'mariadb:11'];
}

echo \json_encode(['matrix' => [
    'test' => [
        ['path' => 'Core/Checkout'],
        ['path' => 'Core/Content'],
        ['path' => 'Core/Framework'],
        ['path' => 'Storefront'],
        ['path' => '{Administration,Elasticsearch}'],
        ['path' => '{Core/Installer,Core/Maintenance,Core/System}'],
        ['testsuite' => 'devops,migration'],
    ],
    'php' => $php,
    'db' => $db,
]], \JSON_THROW_ON_ERROR);