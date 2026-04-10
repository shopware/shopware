<?php declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Console\Command\Command;

$databaseUrl = getenv('DATABASE_URL');
if (!$databaseUrl) {
    echo "DATABASE_URL is not set.\n";

    return Command::FAILURE;
}

try {
    $dsnParser = new DsnParser(['mysql' => 'pdo_mysql']);
    $params = $dsnParser->parse($databaseUrl);
    $connection = DriverManager::getConnection($params);

    $timestamp = '2020-01-01 00:00:00.000000';

    // To trigger the consent of Product analytics, admin User creation date needs to be older than 15 days and the earliest migration update date needs to be older than 60 days.
    $connection->executeStatement(
        'UPDATE `user` SET `created_at` = :timestamp WHERE `admin` = 1',
        ['timestamp' => $timestamp]
    );

    $connection->executeStatement(
        'UPDATE `migration` SET `update` = :timestamp ORDER BY `update` ASC LIMIT 1',
        ['timestamp' => $timestamp]
    );
} catch (Throwable $e) {
    echo "Failed to prepare Product Analytics consent preconditions: {$e->getMessage()}\n";

    return Command::FAILURE;
}

echo "Prepared Product Analytics consent preconditions.\n";
