<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[CoversClass(MigrationStep::class)]
class InstallEnvironmentTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SERVER[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE]);
        unset($_ENV[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE]);
    }

    protected function tearDown(): void
    {
        unset($_SERVER[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE]);
        unset($_ENV[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE]);
    }

    public function testInstallEnvironmentNotSet(): void
    {
        $migration = new ExampleMigration();

        static::assertFalse($migration->isInstallation());
    }

    public function testInstallServerVariableSetTrue(): void
    {
        $_SERVER[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE] = true;
        $migration = new ExampleMigration();

        static::assertTrue($migration->isInstallation());
    }

    public function testInstallServerVariableSetFalse(): void
    {
        $_SERVER[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE] = false;
        $migration = new ExampleMigration();

        static::assertFalse($migration->isInstallation());
    }

    public function testInstallEnvironmentSetTrue(): void
    {
        $_ENV[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE] = true;
        $migration = new ExampleMigration();

        static::assertTrue($migration->isInstallation());
    }

    public function testInstallEnvironmentSetFalse(): void
    {
        $_ENV[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE] = false;
        $migration = new ExampleMigration();

        static::assertFalse($migration->isInstallation());
    }
}

/**
 * @internal
 */
class ExampleMigration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1536232600;
    }

    public function update(Connection $connection): void
    {
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
