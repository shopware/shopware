<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Maintenance\System\Command\SystemSetupCommand;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Dotenv\Command\DotenvDumpCommand;
use Symfony\Component\Dotenv\Dotenv;

/**
 * @internal
 */
#[CoversClass(SystemSetupCommand::class)]
class SystemSetupCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(__DIR__ . '/.env');
        @unlink(__DIR__ . '/symfony.lock');
        @unlink(__DIR__ . '/.env.local.php');
        @unlink(__DIR__ . '/config/jwt/private.pem');
        @unlink(__DIR__ . '/config/jwt/public.pem');
        @rmdir(__DIR__ . '/config/jwt');
        @rmdir(__DIR__ . '/config');
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
            '--http-cache-ttl' => '7200',
            '--cdn-strategy' => 'id',
            '--blue-green' => '1',
            '--mailer-url' => 'smtp://localhost:25',
            '--composer-home' => __DIR__,
        ];

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false]);

        $tester->assertCommandIsSuccessful();

        static::assertFileExists(__DIR__ . '/.env');
        static::assertFileDoesNotExist(__DIR__ . '/.env.local.php');

        $envContent = file_get_contents(__DIR__ . '/.env');
        static::assertIsString($envContent);
        $env = (new Dotenv())->parse($envContent);

        static::assertArrayHasKey('APP_SECRET', $env);
        static::assertArrayHasKey('INSTANCE_ID', $env);
        unset($env['APP_SECRET'], $env['INSTANCE_ID']);
        unset($env['DATABASE_SSL_DONT_VERIFY_SERVER_CERT']);
        static::assertEquals([
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
            'SHOPWARE_HTTP_DEFAULT_TTL' => '7200',
            'SHOPWARE_CDN_STRATEGY_DEFAULT' => 'id',
            'BLUE_GREEN_DEPLOYMENT' => '1',
            'MAILER_DSN' => 'smtp://localhost:25',
            'COMPOSER_HOME' => __DIR__,
        ], $env);
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
            '--http-cache-ttl' => '7200',
            '--cdn-strategy' => 'id',
            '--blue-green' => '1',
            '--mailer-url' => 'smtp://localhost:25',
            '--composer-home' => __DIR__,
            '--dump-env' => true,
        ];

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false]);

        $tester->assertCommandIsSuccessful();

        static::assertFileExists(__DIR__ . '/.env');
        static::assertFileExists(__DIR__ . '/.env.local.php');

        $envContent = file_get_contents(__DIR__ . '/.env');
        static::assertIsString($envContent);
        $env = (new Dotenv())->parse($envContent);

        $envLocal = require __DIR__ . '/.env.local.php';
        static::assertEquals($env, $envLocal);
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
            '--http-cache-ttl' => '7200',
            '--cdn-strategy' => 'id',
            '--blue-green' => '1',
            '--mailer-url' => 'smtp://localhost:25',
            '--composer-home' => __DIR__,
        ];

        touch(__DIR__ . '/symfony.lock');

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false, 'verbosity' => OutputInterface::VERBOSITY_DEBUG]);

        $tester->assertCommandIsSuccessful();

        static::assertStringContainsString('It looks like you have installed Shopware with Symfony Flex', $tester->getDisplay());
    }

    private function getApplicationTester(): ApplicationTester
    {
        $dumpCommand = new DotenvDumpCommand(__DIR__);

        $application = new Application();
        $application->setAutoExit(false);
        $application->add(
            new SystemSetupCommand(
                __DIR__,
                new JwtCertificateGenerator(),
                $dumpCommand
            )
        );

        $application->add($dumpCommand);

        return new ApplicationTester(
            $application,
        );
    }
}
