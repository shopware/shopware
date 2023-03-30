<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

trait MigrationTestBehaviour
{
    /**
     * @before
     */
    public function addMigrationSources(): void
    {
        $loader = $this->getContainer()->get(MigrationCollectionLoader::class);

        $loader->addSource(
            new MigrationSource(
                '_test_migrations_invalid_namespace',
                [__DIR__ . '/_test_migrations_invalid_namespace' => 'Shopware\Core\Framework\Test\Migration\_test_migrations_invalid_namespace']
            )
        );

        $loader->addSource(
            new MigrationSource(
                '_test_migrations_valid',
                [__DIR__ . '/_test_migrations_valid' => 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid']
            )
        );

        $loader->addSource(
            new MigrationSource(
                '_test_migrations_valid_run_time',
                [__DIR__ . '/_test_migrations_valid_run_time' => 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time']
            )
        );

        $loader->addSource(
            new MigrationSource(
                '_test_migrations_valid_run_time_exceptions',
                [__DIR__ . '/_test_migrations_valid_run_time_exceptions' => 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time_exceptions']
            )
        );

        $loader->addSource(
            new MigrationSource(
                '_test_trigger_with_trigger_',
                [__DIR__ . '/_test_trigger_with_trigger_' => 'Shopware\Core\Framework\Test\Migration\_test_trigger_with_trigger_']
            )
        );

        $this->getContainer()->get(MigrationCollectionLoader::class)->addSource(
            new MigrationSource(
                self::INTEGRATION_IDENTIFIER(),
                [__DIR__ . '/_test_migrations_valid' => 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid']
            )
        );

        $this->getContainer()->get(MigrationCollectionLoader::class)->addSource(
            new MigrationSource(
                self::INTEGRATION_WITH_EXCEPTION_IDENTIFIER(),
                [
                    __DIR__ . '/_test_migrations_valid' => 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid',
                    __DIR__ . '/_test_migrations_valid_run_time_exceptions' => 'Shopware\Core\Framework\Test\Migration\_test_migrations_valid_run_time_exceptions',
                ]
            )
        );
    }

    protected static function INTEGRATION_IDENTIFIER(): string
    {
        return 'intergration';
    }

    protected static function INTEGRATION_WITH_EXCEPTION_IDENTIFIER(): string
    {
        return 'integration_with_exception';
    }

    protected function getMigrationCollection(string $name): MigrationCollection
    {
        return $this->getContainer()->get(MigrationCollectionLoader::class)->collect($name);
    }

    protected function assertMigrationState(MigrationCollection $migrationCollection, int $expectedCount, ?int $updateUntil = null, ?int $destructiveUntil = null): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        /** @var MigrationSource $migrationSource */
        $migrationSource = ReflectionHelper::getPropertyValue($migrationCollection, 'migrationSource');

        $dbMigrations = $connection
            ->fetchAllAssociative(
                'SELECT * FROM `migration` WHERE `class` REGEXP :pattern ORDER BY `creation_timestamp`',
                ['pattern' => $migrationSource->getNamespacePattern()]
            );

        TestCase::assertCount($expectedCount, $dbMigrations);

        $assertState = static function (array $dbMigrations, $until, $key): void {
            foreach ($dbMigrations as $migration) {
                if ($migration['creation_timestamp'] <= $until && $migration[$key] === null) {
                    TestCase::fail('Too few migrations have "' . $key . '"' . print_r($dbMigrations, true));
                }

                if ($migration['creation_timestamp'] > $until && $migration[$key] !== null) {
                    TestCase::fail('Too many migrations have "' . $key . '"' . print_r($dbMigrations, true));
                }
            }
        };

        $assertState($dbMigrations, $updateUntil, 'update');
        $assertState($dbMigrations, $destructiveUntil, 'update_destructive');
    }

    abstract protected static function getContainer(): ContainerInterface;
}
