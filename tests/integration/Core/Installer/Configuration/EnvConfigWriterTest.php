<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Installer\Configuration;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Installer\Configuration\EnvConfigWriter;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class EnvConfigWriterTest extends TestCase
{
    private Filesystem $filesystem;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . '/' . uniqid('shopware-env-config-writer-test', true);
        $this->filesystem->dumpFile($this->projectDir . '/public/.htaccess.dist', "test content\n");
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testWriteConfig(): void
    {
        $writer = new EnvConfigWriter($this->projectDir);

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

        static::assertFileExists($this->projectDir . '/.env');
        static::assertFileExists($this->projectDir . '/.env.local');
        $content = $this->filesystem->readFile($this->projectDir . '/.env.local');
        static::assertStringNotContainsString('DATABASE_SSL_CA', $content);
        static::assertStringContainsString('DATABASE_URL=' . $info->asDsn(), $content);
        static::assertStringContainsString('APP_URL=https://localhost/shop', $content);
        static::assertStringContainsString('BLUE_GREEN_DEPLOYMENT=1', $content);
        static::assertMatchesRegularExpression('/^INSTANCE_ID=[0-9a-zA-Z]{32}$/m', $content);

        static::assertFileExists($this->projectDir . '/public/.htaccess');
        static::assertFileEquals($this->projectDir . '/public/.htaccess.dist', $this->projectDir . '/public/.htaccess');
    }

    public function testWriteWithSSLConfig(): void
    {
        $writer = new EnvConfigWriter($this->projectDir);

        $info = new DatabaseConnectionInformation();
        $info->assign([
            'hostname' => 'localhost',
            'port' => 3306,
            'username' => 'root',
            'password' => 'root',
            'databaseName' => 'shopware',
            'sslCaPath' => '/foo.ca.crt',
            'sslCertPath' => '/foo.crt',
            'sslCertKeyPath' => '/foo.key',
            'sslDontVerifyServerCert' => true,
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

        static::assertFileExists($this->projectDir . '/.env.local');
        $content = $this->filesystem->readFile($this->projectDir . '/.env.local');
        static::assertStringContainsString('DATABASE_URL=' . $info->asDsn(), $content);
        static::assertStringContainsString('APP_URL=https://localhost/shop', $content);
        static::assertStringContainsString('BLUE_GREEN_DEPLOYMENT=1', $content);
        static::assertMatchesRegularExpression('/^INSTANCE_ID=[0-9a-zA-Z]{32}$/m', $content);
        static::assertStringContainsString('DATABASE_SSL_CA=/foo.ca.crt', $content);
        static::assertStringContainsString('DATABASE_SSL_CERT=/foo.crt', $content);
        static::assertStringContainsString('DATABASE_SSL_KEY=/foo.key', $content);
        static::assertStringContainsString('DATABASE_SSL_DONT_VERIFY_SERVER_CERT=1', $content);
    }
}
