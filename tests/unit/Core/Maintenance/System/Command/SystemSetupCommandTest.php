<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\System\Command\SystemSetupCommand;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Dotenv\Command\DotenvDumpCommand;
use Symfony\Component\Dotenv\Dotenv;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemSetupCommand::class)]
class SystemSetupCommandTest extends TestCase
{
    private ?string $tempProjectDir = null;

    private bool $hadComposerHomeServerVar = false;

    private mixed $composerHomeServerVar = null;

    private bool $hadComposerHomeEnvVar = false;

    private mixed $composerHomeEnvVar = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hadComposerHomeServerVar = \array_key_exists('COMPOSER_HOME', $_SERVER);
        $this->composerHomeServerVar = $_SERVER['COMPOSER_HOME'] ?? null;
        $this->hadComposerHomeEnvVar = \array_key_exists('COMPOSER_HOME', $_ENV);
        $this->composerHomeEnvVar = $_ENV['COMPOSER_HOME'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->hadComposerHomeServerVar) {
            $_SERVER['COMPOSER_HOME'] = $this->composerHomeServerVar;
        } else {
            unset($_SERVER['COMPOSER_HOME']);
        }

        if ($this->hadComposerHomeEnvVar) {
            $_ENV['COMPOSER_HOME'] = $this->composerHomeEnvVar;
        } else {
            unset($_ENV['COMPOSER_HOME']);
        }

        if ($this->tempProjectDir !== null) {
            @unlink($this->tempProjectDir . '/.env');
            @rmdir($this->tempProjectDir);
        }

        @unlink(__DIR__ . '/.env');
        @unlink(__DIR__ . '/symfony.lock');
        @unlink(__DIR__ . '/.env.local.php');

        parent::tearDown();
    }

    #[TestDox('uses the composer home option unless it is falsy')]
    #[DataProvider('composerHomeOptionProvider')]
    public function testComposerHomeFromOption(mixed $option, string $expected): void
    {
        static::assertSame(
            str_replace('%projectDir%', $this->tempProjectDir(), $expected),
            $this->runSetupAndReadComposerHome(['--composer-home' => $option]),
        );
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function composerHomeOptionProvider(): iterable
    {
        yield 'empty string' => ['', '%projectDir%/var/cache/composer'];
        yield 'zero string' => ['0', '%projectDir%/var/cache/composer'];
        yield 'boolean false' => [false, '%projectDir%/var/cache/composer'];
        yield 'boolean true' => [true, '%projectDir%/var/cache/composer'];
        yield 'path' => ['/custom/composer', '/custom/composer'];
        yield 'double zero string' => ['00', '00'];
        yield 'space' => [' ', ' '];
    }

    public function testComposerHomeFallsBackWithoutOptionOrEnvironment(): void
    {
        unset($_SERVER['COMPOSER_HOME'], $_ENV['COMPOSER_HOME']);

        static::assertSame(
            $this->tempProjectDir() . '/var/cache/composer',
            $this->runSetupAndReadComposerHome([]),
        );
    }

    #[TestDox('uses the composer home environment variable unless it is falsy')]
    #[DataProvider('composerHomeEnvironmentProvider')]
    public function testComposerHomeFromEnvironment(string $environment, string $expected): void
    {
        $_SERVER['COMPOSER_HOME'] = $environment;

        static::assertSame(
            str_replace('%projectDir%', $this->tempProjectDir(), $expected),
            $this->runSetupAndReadComposerHome([]),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function composerHomeEnvironmentProvider(): iterable
    {
        yield 'empty string' => ['', '%projectDir%/var/cache/composer'];
        yield 'zero string' => ['0', '%projectDir%/var/cache/composer'];
        yield 'path' => ['/env/composer', '/env/composer'];
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
        unset($env['APP_SECRET'], $env['INSTANCE_ID'], $env['DATABASE_SSL_DONT_VERIFY_SERVER_CERT']);
        static::assertSame([
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
            'COMPOSER_HOME' => __DIR__,
        ], $env);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEnvFileGenerationWithHttpDefaultTtl(): void
    {
        $args = [
            'command' => 'system:setup',
            '--http-cache-ttl' => '7201',
        ];

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false]);

        $tester->assertCommandIsSuccessful();

        static::assertFileExists(__DIR__ . '/.env');

        $envContent = file_get_contents(__DIR__ . '/.env');
        static::assertIsString($envContent);
        $env = (new Dotenv())->parse($envContent);

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

        /** @phpstan-ignore require.fileNotFound (Although the existence of the file is checked above, PHPStan will only consider files, that always exist. See https://github.com/phpstan/phpstan/issues/12417) */
        $envLocal = require __DIR__ . '/.env.local.php';
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
            '--composer-home' => __DIR__,
        ];

        touch(__DIR__ . '/symfony.lock');

        $tester = $this->getApplicationTester();

        $tester->run($args, ['interactive' => false, 'verbosity' => OutputInterface::VERBOSITY_DEBUG]);

        $tester->assertCommandIsSuccessful();

        static::assertStringContainsString('It looks like you have installed Shopware with Symfony Flex', $tester->getDisplay());
    }

    private function tempProjectDir(): string
    {
        if ($this->tempProjectDir === null) {
            $dir = sys_get_temp_dir() . '/system-setup-' . bin2hex(random_bytes(8));
            mkdir($dir);
            $this->tempProjectDir = $dir;
        }

        return $this->tempProjectDir;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runSetupAndReadComposerHome(array $options): string
    {
        $projectDir = $this->tempProjectDir();
        $dumpCommand = new DotenvDumpCommand($projectDir);

        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand(new SystemSetupCommand($projectDir, $dumpCommand));

        $tester = new ApplicationTester($application);
        $tester->run([
            'command' => 'system:setup',
            '--app-env' => 'test',
            '--app-url' => 'https://example.com',
            '--database-url' => 'mysql://localhost:3306/shopware',
            ...$options,
        ], ['interactive' => false]);

        $tester->assertCommandIsSuccessful();

        $envContent = file_get_contents($projectDir . '/.env');
        static::assertIsString($envContent);
        $env = (new Dotenv())->parse($envContent);
        static::assertArrayHasKey('COMPOSER_HOME', $env);

        return $env['COMPOSER_HOME'];
    }

    private function getApplicationTester(): ApplicationTester
    {
        $dumpCommand = new DotenvDumpCommand(__DIR__);

        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand(new SystemSetupCommand(__DIR__, $dumpCommand));

        $application->addCommand($dumpCommand);

        return new ApplicationTester($application);
    }
}
