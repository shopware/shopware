#!/usr/bin/env php
<?php

date_default_timezone_set('UTC');

$longopts = [
    'username:',
    'password:',
    'host:',
    'dbname:',
];
$deployConfig = getopt('', ['tablesuffix::', 'shoppath:', 'migrationpath:']);
$dbConfig = getopt('', $longopts);
$shopPath = $deployConfig['shoppath'];

if (!isset($dbConfig['host']) || empty($dbConfig['host'])) {
    $dbConfig['host'] = 'localhost';
}
$password = isset($dbConfig['password']) ? $dbConfig['password'] : '';
$connectionSettings = [
    'host=' . $dbConfig['host'],
    'dbname=' . $dbConfig['dbname'],
];
if (!empty($dbConfig['socket'])) {
    $connectionSettings[] = 'unix_socket=' . $dbConfig['socket'];
}
if (!empty($dbConfig['port'])) {
    $connectionSettings[] = 'port=' . $dbConfig['port'];
}
$connectionString = implode(';', $connectionSettings);
try {
    $conn = new PDO(
        'mysql:' . $connectionString,
        $dbConfig['username'],
        $password,
        [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"]
    );
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo 'Could not connect to database: ' . $e->getMessage();
    exit(1);
}

require __DIR__ . '/../src/Framework/Migration/AbstractMigration.php';
require __DIR__ . '/../src/Framework/Migration/Manager.php';

$modeArg = getopt('', ['mode:']);
if (!isset($modeArg['mode']) || $modeArg['mode'] === 'install') {
    $mode = \Shopware\Framework\Migration\AbstractMigration::MODUS_INSTALL;
} else {
    $mode = \Shopware\Framework\Migration\AbstractMigration::MODUS_UPDATE;
}
$migrationManger = new Shopware\Framework\Migration\Manager($conn, $deployConfig['migrationpath']);

$migrationManger->run($mode);
