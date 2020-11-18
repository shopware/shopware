<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class InstallEnvironmentTest extends TestCase
{
    use KernelTestBehaviour;

    public function setup(): void
    {
        unset($_SERVER[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE]);
        unset($_ENV[MigrationStep::INSTALL_ENVIRONMENT_VARIABLE]);
    }

    public function tearDown(): void
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
