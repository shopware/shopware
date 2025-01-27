<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
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

        static::assertInstanceOf(MigrationException::class, $exception);
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__MIGRATION_INVALID_ARGUMENT_EXCEPTION', $exception->getErrorCode());
        static::assertSame('invalid', $exception->getMessage());
    }

    /**
     * @deprecated tag:v6.7.0 - reason: see MigrationException::invalidArgument - to be removed
     */
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testInvalidArgumentDeprecated(): void
    {
        $exception = MigrationException::invalidArgument('test');
        static::assertInstanceOf(\InvalidArgumentException::class, $exception);
        static::assertSame('test', $exception->getMessage());
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
}
