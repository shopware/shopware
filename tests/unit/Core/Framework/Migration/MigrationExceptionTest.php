<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationException::class)]
class MigrationExceptionTest extends TestCase
{
    public function testInvalidVersionSelectionMode(): void
    {
        $exception = MigrationException::invalidVersionSelectionMode('invalid');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__MIGRATION_INVALID_VERSION_SELECTION_MODE', $exception->getErrorCode());
        static::assertSame('Version selection mode needs to be one of these values: "all", "blue-green", "safe", but "invalid" was given.', $exception->getMessage());
        static::assertSame(['validModes' => 'all", "blue-green", "safe', 'mode' => 'invalid'], $exception->getParameters());
    }

    public function testInvalidArgument(): void
    {
        $exception = MigrationException::invalidArgument('invalid');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__MIGRATION_INVALID_ARGUMENT_EXCEPTION', $exception->getErrorCode());
        static::assertSame('invalid', $exception->getMessage());
    }

    public function testMoreThanOnePluginFound(): void
    {
        $exception = MigrationException::moreThanOnePluginFound('plugin', ['plugin1', 'plugin2']);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__MIGRATION_MORE_THAN_ONE_PLUGIN_FOUND', $exception->getErrorCode());
        static::assertSame('More than one plugin name starting with "plugin" was found: plugin1;plugin2', $exception->getMessage());
        static::assertSame(['pluginName' => 'plugin', 'plugins' => 'plugin1;plugin2'], $exception->getParameters());
    }

    public function testMigrationDirectoryNotCreated(): void
    {
        $exception = MigrationException::migrationDirectoryNotCreated('test');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__MIGRATION_DIRECTORY_COULD_NOT_BE_CREATED', $exception->getErrorCode());
        static::assertSame('Migration directory "test" could not be created', $exception->getMessage());
        static::assertSame(['directory' => 'test'], $exception->getParameters());
    }

    public function testPluginNotFound(): void
    {
        $exception = MigrationException::pluginNotFound('test');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__MIGRATION_PLUGIN_COULD_NOT_BE_FOUND', $exception->getErrorCode());
        static::assertSame('Plugin "test" could not be found.', $exception->getMessage());
        static::assertSame(['pluginName' => 'test'], $exception->getParameters());
    }

    public function testMigrationDirectoryNotReadable(): void
    {
        $exception = MigrationException::migrationDirectoryNotReadable('/tmp/nope');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__MIGRATION_DIRECTORY_NOT_READABLE', $exception->getErrorCode());
        static::assertSame('Migration directory "/tmp/nope" could not be read.', $exception->getMessage());
        static::assertSame(['directory' => '/tmp/nope'], $exception->getParameters());
    }

    public function testReversibleMigrationNotInstantiable(): void
    {
        $exception = MigrationException::reversibleMigrationNotInstantiable('Acme\\Migration1');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__REVERSIBLE_MIGRATION_NOT_INSTANTIABLE', $exception->getErrorCode());
        static::assertSame('Reversible migration "Acme\\Migration1" must be instantiable without constructor arguments.', $exception->getMessage());
        static::assertSame(['class' => 'Acme\\Migration1'], $exception->getParameters());
    }

    public function testReversibleMigrationInvalidTimestamp(): void
    {
        $exception = MigrationException::reversibleMigrationInvalidTimestamp('Acme\\Migration1', 0);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__REVERSIBLE_MIGRATION_INVALID_TIMESTAMP', $exception->getErrorCode());
        static::assertSame('Migration timestamp must be between 1 and 2147483647 to ensure migration order is deterministic on every system, but "0" was given for "Acme\\Migration1".', $exception->getMessage());
        static::assertSame(['class' => 'Acme\\Migration1', 'timestamp' => 0], $exception->getParameters());
    }

    public function testDuplicateMigrationTimestamp(): void
    {
        $exception = MigrationException::duplicateMigrationTimestamp('SwagTest', 100, 'Acme\\A', 'Acme\\B');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__REVERSIBLE_MIGRATION_DUPLICATE_TIMESTAMP', $exception->getErrorCode());
        static::assertSame('Plugin "SwagTest" contains two reversible migrations with timestamp 100: "Acme\\A" and "Acme\\B".', $exception->getMessage());
        static::assertSame(['plugin' => 'SwagTest', 'timestamp' => 100, 'first' => 'Acme\\A', 'second' => 'Acme\\B'], $exception->getParameters());
    }

    public function testMigrationOutOfOrder(): void
    {
        $exception = MigrationException::migrationOutOfOrder('SwagTest', 'Acme\\A', 100, 200);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__REVERSIBLE_MIGRATION_OUT_OF_ORDER', $exception->getErrorCode());
        static::assertSame('Reversible migration "Acme\\A" (100) for plugin "SwagTest" is older than the latest applied migration (200). Add new migrations with a later timestamp.', $exception->getMessage());
        static::assertSame(['plugin' => 'SwagTest', 'class' => 'Acme\\A', 'timestamp' => 100, 'latestApplied' => 200], $exception->getParameters());
    }

    public function testMigrationTimestampChanged(): void
    {
        $exception = MigrationException::migrationTimestampChanged('Acme\\A', 100, 200);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__REVERSIBLE_MIGRATION_TIMESTAMP_CHANGED', $exception->getErrorCode());
        static::assertSame('Applied reversible migration "Acme\\A" changed its creation timestamp from 100 to 200. Restore the recorded timestamp.', $exception->getMessage());
        static::assertSame(['class' => 'Acme\\A', 'recorded' => 100, 'declared' => 200], $exception->getParameters());
    }

    public function testMissingAppliedMigration(): void
    {
        $exception = MigrationException::missingAppliedMigration('SwagTest', 'Acme\\A');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__REVERSIBLE_MIGRATION_MISSING_APPLIED', $exception->getErrorCode());
        static::assertSame('Applied reversible migration "Acme\\A" for plugin "SwagTest" is no longer available. Restore the migration class before continuing.', $exception->getMessage());
        static::assertSame(['plugin' => 'SwagTest', 'class' => 'Acme\\A'], $exception->getParameters());
    }

    public function testMigrationLockNotAcquired(): void
    {
        $exception = MigrationException::migrationLockNotAcquired('SwagTest');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__REVERSIBLE_MIGRATION_LOCK_NOT_ACQUIRED', $exception->getErrorCode());
        static::assertSame('Could not acquire the reversible migration lock for plugin "SwagTest".', $exception->getMessage());
        static::assertSame(['plugin' => 'SwagTest'], $exception->getParameters());
    }

    public function testInvalidMigrationState(): void
    {
        $exception = MigrationException::invalidMigrationState();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__REVERSIBLE_MIGRATION_INVALID_STATE', $exception->getErrorCode());
        static::assertSame('The reversible migration state contains an invalid row.', $exception->getMessage());
    }
}
