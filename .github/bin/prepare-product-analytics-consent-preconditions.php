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
    $prepareModalState = getenv('PRODUCT_ANALYTICS_PREPARE_MODAL_STATE') === '1';

    // To trigger the consent of Product analytics, admin User creation date needs to be older than 15 days and the earliest migration update date needs to be older than 60 days.
    $connection->executeStatement(
        'UPDATE `user` SET `created_at` = :timestamp WHERE `admin` = 1',
        ['timestamp' => $timestamp]
    );

    $connection->executeStatement(
        'UPDATE `migration` SET `update` = :timestamp ORDER BY `update` ASC LIMIT 1',
        ['timestamp' => $timestamp]
    );

    if ($prepareModalState) {
        // The Product Analytics consent modal is only rendered while both
        // consents are still in their implicit initial state, which means no
        // consent_state row exists for them yet.
        $connection->executeStatement(
            'DELETE FROM `consent_state` WHERE `name` IN (:consents)',
            ['consents' => ['backend_data', 'product_analytics']],
            ['consents' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );
    }
} catch (Throwable $e) {
    echo "Failed to prepare Product Analytics consent preconditions: {$e->getMessage()}\n";

    return Command::FAILURE;
}

echo "Prepared Product Analytics consent preconditions.\n";
