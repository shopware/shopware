<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Configuration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Configuration\EnvConfigWriter;
use Shopware\Core\Installer\Finish\UniqueIdGenerator;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

/**
 * @internal
 * @covers \Shopware\Core\Installer\Configuration\EnvConfigWriter
 */
class EnvConfigWriterTest extends TestCase
{
    public function tearDown(): void
    {
        unlink(__DIR__ . '/_fixtures/.env');
        unlink(__DIR__ . '/_fixtures/public/.htaccess');
    }

    public function testWriteConfig(): void
    {
        $idGenerator = $this->createMock(UniqueIdGenerator::class);
        $idGenerator->expects(static::once())->method('getUniqueId')
            ->willReturn('1234567890');

        $writer = new EnvConfigWriter(__DIR__ . '/_fixtures', $idGenerator);

        $info = new DatabaseConnectionInformation();
        $info->assign([
            'hostname' => 'localhost',
            'port' => 3306,
            'username' => 'root',
            'password' => 'root',
            'databaseName' => 'shopware',
        ]);

        $writer->writeConfig($info, [
            'name' => 'test',
            'locale' => 'de-DE',
            'currency' => 'EUR',
            'additionalCurrencies' => [],
            'country' => 'DEU',
            'email' => 'test@test.com',
            'host' => 'localhost',
            'schema' => 'https',
            'basePath' => '/shop',
            'blueGreenDeployment' => true,
        ]);

        static::assertFileExists(__DIR__ . '/_fixtures/.env');
        $content = \file_get_contents(__DIR__ . '/_fixtures/.env');
        static::assertIsString($content);
        static::assertStringContainsString('DATABASE_URL="' . $info->asDsn() . '"', $content);
        static::assertStringContainsString('APP_URL="https://localhost/shop"', $content);
        static::assertStringContainsString('BLUE_GREEN_DEPLOYMENT="1"', $content);
        static::assertStringContainsString('INSTANCE_ID="1234567890"', $content);

        static::assertFileExists(__DIR__ . '/_fixtures/public/.htaccess');
        static::assertFileEquals(__DIR__ . '/_fixtures/public/.htaccess.dist', __DIR__ . '/_fixtures/public/.htaccess');
    }
}
