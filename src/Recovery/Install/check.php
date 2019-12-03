<?php declare(strict_types=1);

error_reporting(-1);
ini_set('display_errors', true);

$tokenFile = __DIR__ . '/tmp/token';
$token = '';
if (is_readable($tokenFile)) {
    $token = file_get_contents($tokenFile);
}
$token = trim($token);

if (!$token
    || empty($token)
    || !isset($_GET['token'])
    || empty($_GET['token'])
    || $token !== $_GET['token']
) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden';
    exit;
}

$result = [
    'phpversion' => phpversion(),
];

echo json_encode($result, JSON_PRETTY_PRINT);
