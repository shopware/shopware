<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Maintenance\System\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\System\Command\SystemSetupCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Dotenv\Command\DotenvDumpCommand;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class SystemSetupCommandTest extends TestCase
{
    private Filesystem $filesystem;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . '/' . uniqid('shopware-system-setup-test', true);
        $this->filesystem->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->projectDir);
    }

    public function testEnvFileGeneration(): void
    {
        $args = [
            'command' => 'system:setup',
            '--app-env' => 'test',
            '--app-url' => 'https://example.com',
            '--database-url' => 'mysql://localhost:3306/shopware',
            '--es-hosts' => 'localhost:9200',
            '--es-enabled' => '1',
            '--es-indexing-enabled' => '1',
            '--es-index-prefix' => 'shopware',
            '--admin-es-hosts' => 'localhost:9200',
            '--admin-es-index-prefix' => 'shopware-admin',
            '--admin-es-enabled' => '1',
            '--admin-es-refresh-indices' => '1',
            '--http-cache-enabled' => '1',
            '--cdn-strategy' => 'id',
            '--blue-green' => '1',
            '--mailer-url' => 'smtp://localhost:25',
            '--composer-home' => $this->projectDir,
        ];

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false]);

        $tester->assertCommandIsSuccessful();

        static::assertFileExists($this->projectDir . '/.env');
        static::assertFileDoesNotExist($this->projectDir . '/.env.local.php');

        $env = (new Dotenv())->parse($this->filesystem->readFile($this->projectDir . '/.env'));

        static::assertArrayHasKey('APP_SECRET', $env);
        static::assertArrayHasKey('INSTANCE_ID', $env);
        unset($env['APP_SECRET'], $env['INSTANCE_ID'], $env['DATABASE_SSL_DONT_VERIFY_SERVER_CERT'], $env['SHOPWARE_HTTP_DEFAULT_TTL']);
        $expected = [
            'APP_ENV' => 'test',
            'APP_URL' => 'https://example.com',
            'DATABASE_URL' => 'mysql://localhost:3306/shopware',
            'OPENSEARCH_URL' => 'localhost:9200',
            'SHOPWARE_ES_ENABLED' => '1',
            'SHOPWARE_ES_INDEXING_ENABLED' => '1',
            'SHOPWARE_ES_INDEX_PREFIX' => 'shopware',
            'ADMIN_OPENSEARCH_URL' => 'localhost:9200',
            'SHOPWARE_ADMIN_ES_INDEX_PREFIX' => 'shopware-admin',
            'SHOPWARE_ADMIN_ES_ENABLED' => '1',
            'SHOPWARE_ADMIN_ES_REFRESH_INDICES' => '1',
            'SHOPWARE_HTTP_CACHE_ENABLED' => '1',
            'SHOPWARE_CDN_STRATEGY_DEFAULT' => 'id',
            'BLUE_GREEN_DEPLOYMENT' => '1',
            'MAILER_DSN' => 'smtp://localhost:25',
            'COMPOSER_HOME' => $this->projectDir,
        ];
        static::assertSame($expected, $env);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public function testEnvFileGenerationWithHttpDefaultTtl(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $args = [
            'command' => 'system:setup',
            '--http-cache-ttl' => '7201',
        ];

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false]);

        $tester->assertCommandIsSuccessful();

        static::assertFileExists($this->projectDir . '/.env');

        $env = (new Dotenv())->parse($this->filesystem->readFile($this->projectDir . '/.env'));

        static::assertArrayHasKey('SHOPWARE_HTTP_DEFAULT_TTL', $env);
        static::assertSame('7201', $env['SHOPWARE_HTTP_DEFAULT_TTL']);
    }

    public function testEnvFileGenerationWithDumpEnv(): void
    {
        $args = [
            'command' => 'system:setup',
            '--app-env' => 'test',
            '--app-url' => 'https://example.com',
            '--database-url' => 'mysql://localhost:3306/shopware',
            '--es-hosts' => 'localhost:9200',
            '--es-enabled' => '1',
            '--es-indexing-enabled' => '1',
            '--es-index-prefix' => 'shopware',
            '--admin-es-hosts' => 'localhost:9200',
            '--admin-es-index-prefix' => 'shopware-admin',
            '--admin-es-enabled' => '1',
            '--admin-es-refresh-indices' => '1',
            '--http-cache-enabled' => '1',
            '--cdn-strategy' => 'id',
            '--blue-green' => '1',
            '--mailer-url' => 'smtp://localhost:25',
            '--composer-home' => $this->projectDir,
            '--dump-env' => true,
        ];

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false]);

        $tester->assertCommandIsSuccessful();

        static::assertFileExists($this->projectDir . '/.env');
        static::assertFileExists($this->projectDir . '/.env.local.php');

        $env = (new Dotenv())->parse($this->filesystem->readFile($this->projectDir . '/.env'));

        $envLocal = require $this->projectDir . '/.env.local.php';
        static::assertSame($env, $envLocal);
    }

    public function testSymfonyFlexGeneratesWarning(): void
    {
        $args = [
            'command' => 'system:setup',
            '-v' => true,
            '--app-env' => 'test',
            '--app-url' => 'https://example.com',
            '--database-url' => 'mysql://localhost:3306/shopware',
            '--es-hosts' => 'localhost:9200',
            '--es-enabled' => '1',
            '--es-indexing-enabled' => '1',
            '--es-index-prefix' => 'shopware',
            '--http-cache-enabled' => '1',
            '--cdn-strategy' => 'id',
            '--blue-green' => '1',
            '--mailer-url' => 'smtp://localhost:25',
            '--composer-home' => $this->projectDir,
        ];

        $this->filesystem->touch($this->projectDir . '/symfony.lock');

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false, 'verbosity' => OutputInterface::VERBOSITY_DEBUG]);

        $tester->assertCommandIsSuccessful();

        static::assertStringContainsString('It looks like you have installed Shopware with Symfony Flex', $tester->getDisplay());
    }

    private function getApplicationTester(): ApplicationTester
    {
        $dumpCommand = new DotenvDumpCommand($this->projectDir);

        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand(new SystemSetupCommand($this->projectDir, $dumpCommand));

        $application->addCommand($dumpCommand);

        return new ApplicationTester($application);
    }
}
