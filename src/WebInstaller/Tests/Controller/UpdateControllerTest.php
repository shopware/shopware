<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\WebInstaller\Controller\UpdateController;
use Shopware\WebInstaller\Services\FlexMigrator;
use Shopware\WebInstaller\Services\ProjectComposerJsonUpdater;
use Shopware\WebInstaller\Services\RecoveryManager;
use Shopware\WebInstaller\Services\ReleaseInfoProvider;
use Shopware\WebInstaller\Services\StreamedCommandResponseGenerator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Router;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(UpdateController::class)]
#[CoversClass(ProjectComposerJsonUpdater::class)]
class UpdateControllerTest extends TestCase
{
    public function testRedirectWhenNotInstalled(): void
    {
        $recoveryManager = $this->createMock(RecoveryManager::class);

        $recoveryManager
            ->method('getShopwareLocation')
            ->willThrowException(new \RuntimeException('Could not find Shopware installation'));

        $controller = new UpdateController(
            $recoveryManager,
            $this->createMock(ReleaseInfoProvider::class),
            $this->createMock(FlexMigrator::class),
            $this->createMock(StreamedCommandResponseGenerator::class),
        );

        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = $controller->index($request);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('configure', $response->headers->get('location'));
    }

    public function testRedirectToFinishWhenNoUpdateThere(): void
    {
        $recoveryManager = $this->createMock(RecoveryManager::class);

        $recoveryManager->method('getShopwareLocation')->willReturn('/path/to/shopware');
        $recoveryManager->method('getCurrentShopwareVersion')->willReturn('6.4.18.0');

        $controller = new UpdateController(
            $recoveryManager,
            $this->createMock(ReleaseInfoProvider::class),
            $this->createMock(FlexMigrator::class),
            $this->createMock(StreamedCommandResponseGenerator::class),
        );
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = $controller->index($request);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('finish', $response->headers->get('location'));
    }

    public function testUpdateThereRendersTemplate(): void
    {
        $recoveryManager = $this->createMock(RecoveryManager::class);

        $recoveryManager->method('getShopwareLocation')->willReturn('/path/to/shopware');
        $recoveryManager->method('getCurrentShopwareVersion')->willReturn('6.4.17.0');

        $controller = new UpdateController(
            $recoveryManager,
            $this->getReleaseInfoProvider(),
            $this->createMock(FlexMigrator::class),
            $this->createMock(StreamedCommandResponseGenerator::class),
        );
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = $controller->index($request);
        $controller->index($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('update.html.twig', $response->getContent());
    }

    public function testMigrateFlex(): void
    {
        $recoveryManager = $this->createMock(RecoveryManager::class);
        $recoveryManager->method('getShopwareLocation')->willReturn('/path/to/shopware');

        $flexMigrator = $this->createMock(FlexMigrator::class);

        $flexMigrator
            ->expects(static::once())
            ->method('cleanup')
            ->with('/path/to/shopware');

        $flexMigrator
            ->expects(static::once())
            ->method('patchRootComposerJson')
            ->with('/path/to/shopware');

        $flexMigrator
            ->expects(static::once())
            ->method('copyNewTemplateFiles')
            ->with('/path/to/shopware');

        $flexMigrator
            ->expects(static::once())
            ->method('migrateEnvFile')
            ->with('/path/to/shopware');

        $controller = new UpdateController(
            $recoveryManager,
            $this->createMock(ReleaseInfoProvider::class),
            $flexMigrator,
            $this->createMock(StreamedCommandResponseGenerator::class),
        );

        $controller->setContainer($this->getContainer());

        $response = $controller->migrateTemplate();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testPrepare(): void
    {
        $recoveryManager = $this->createMock(RecoveryManager::class);

        $recoveryManager->method('getShopwareLocation')->willReturn('/path/to/shopware');
        $recoveryManager->method('getCurrentShopwareVersion')->willReturn('6.4.17.0');
        $recoveryManager->method('getPHPBinary')->willReturn('/usr/bin/php');

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator
            ->expects(static::once())
            ->method('runJSON')
            ->with([
                '/usr/bin/php',
                '-dmemory_limit=1G',
                '/path/to/shopware/bin/console',
                'system:update:prepare',
                '--no-interaction',
            ])
            ->willReturn(new StreamedResponse());

        $controller = new UpdateController(
            $recoveryManager,
            $this->createMock(ReleaseInfoProvider::class),
            $this->createMock(FlexMigrator::class),
            $responseGenerator,
        );
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = $controller->prepare($request);

        static::assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testFinishUpdate(): void
    {
        $recoveryManager = $this->createMock(RecoveryManager::class);

        $recoveryManager->method('getShopwareLocation')->willReturn('/path/to/shopware');
        $recoveryManager->method('getCurrentShopwareVersion')->willReturn('6.4.17.0');
        $recoveryManager->method('getPHPBinary')->willReturn('/usr/bin/php');

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator
            ->expects(static::once())
            ->method('runJSON')
            ->with([
                '/usr/bin/php',
                '-dmemory_limit=1G',
                '/path/to/shopware/bin/console',
                'system:update:finish',
                '--no-interaction',
            ])
            ->willReturn(new StreamedResponse());

        $controller = new UpdateController(
            $recoveryManager,
            $this->createMock(ReleaseInfoProvider::class),
            $this->createMock(FlexMigrator::class),
            $responseGenerator,
        );
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = $controller->finish($request);

        static::assertInstanceOf(StreamedResponse::class, $response);
    }

    #[DataProvider('provideVersions')]
    public function testUpdateChangesComposerJSON(string $shopwareVersion): void
    {
        $recoveryManager = $this->createMock(RecoveryManager::class);

        $fs = new Filesystem();
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('test', true);
        $fs->mkdir($tmpDir);

        $fs->dumpFile($tmpDir . '/composer.json', json_encode([
            'require' => [
                'shopware/core' => '6.1.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        $recoveryManager->method('getShopwareLocation')->willReturn($tmpDir);
        $recoveryManager->method('getCurrentShopwareVersion')->willReturn($shopwareVersion);
        $recoveryManager->method('getBinary')->willReturn('/var/www/shopware-installer.phar.php');
        $recoveryManager->method('getPHPBinary')->willReturn('/usr/bin/php');

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator
            ->expects(static::once())
            ->method('runJSON')
            ->with([
                '/usr/bin/php',
                '-dmemory_limit=1G',
                '/var/www/shopware-installer.phar.php',
                'update',
                '-d',
                $tmpDir,
                '--no-interaction',
                '--no-ansi',
                '--no-scripts',
                '-v',
                '--with-all-dependencies',
            ])
            ->willReturn(new StreamedResponse());

        $controller = new UpdateController(
            $recoveryManager,
            $this->createMock(ReleaseInfoProvider::class),
            $this->createMock(FlexMigrator::class),
            $responseGenerator,
        );
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->query->set('shopwareVersion', '6.4.15.0');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = $controller->run($request);

        /** @var array{require: array<string, string>} $json */
        $json = json_decode((string) file_get_contents($tmpDir . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('6.4.15.0', $json['require']['shopware/core']);

        static::assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testUpdateToRC(): void
    {
        $recoveryManager = $this->createMock(RecoveryManager::class);

        $fs = new Filesystem();
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('test', true);
        $fs->mkdir($tmpDir);

        $fs->dumpFile($tmpDir . '/composer.json', json_encode([
            'require' => [
                'shopware/core' => '6.4.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        $recoveryManager->method('getShopwareLocation')->willReturn($tmpDir);
        $recoveryManager->method('getCurrentShopwareVersion')->willReturn('6.4.0');
        $recoveryManager->method('getBinary')->willReturn('/var/www/shopware-installer.phar.php');
        $recoveryManager->method('getPHPBinary')->willReturn('/usr/bin/php');

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator
            ->expects(static::once())
            ->method('runJSON')
            ->with([
                '/usr/bin/php',
                '-dmemory_limit=1G',
                '/var/www/shopware-installer.phar.php',
                'update',
                '-d',
                $tmpDir,
                '--no-interaction',
                '--no-ansi',
                '--no-scripts',
                '-v',
                '--with-all-dependencies',
            ])
            ->willReturn(new StreamedResponse());

        $controller = new UpdateController(
            $recoveryManager,
            $this->createMock(ReleaseInfoProvider::class),
            $this->createMock(FlexMigrator::class),
            $responseGenerator,
        );
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->query->set('shopwareVersion', '6.5.0.0-rc1');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = $controller->run($request);

        /** @var array{minimum-stability: string, require: array<string, string>} $json */
        $json = json_decode((string) file_get_contents($tmpDir . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('6.5.0.0-rc1', $json['require']['shopware/core']);
        static::assertSame('RC', $json['minimum-stability']);

        static::assertInstanceOf(StreamedResponse::class, $response);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideVersions(): iterable
    {
        yield 'old major' => [
            '6.3.5.0',
        ];

        yield 'current major' => [
            '6.4.17.0',
        ];
    }

    public function testUpdateChangesComposerJSONInTestMode(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.5.9.9';

        $recoveryManager = $this->createMock(RecoveryManager::class);

        $fs = new Filesystem();
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('test', true);
        $fs->mkdir($tmpDir);

        $fs->dumpFile($tmpDir . '/composer.json', json_encode([
            'require' => [
                'shopware/core' => '6.1.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        $recoveryManager->method('getShopwareLocation')->willReturn($tmpDir);
        $recoveryManager->method('getCurrentShopwareVersion')->willReturn('6.4.17.2');
        $recoveryManager->method('getBinary')->willReturn('/var/www/shopware-installer.phar.php');
        $recoveryManager->method('getPHPBinary')->willReturn('/usr/bin/php');

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator
            ->expects(static::once())
            ->method('runJSON')
            ->with([
                '/usr/bin/php',
                '-dmemory_limit=1G',
                '/var/www/shopware-installer.phar.php',
                'update',
                '-d',
                $tmpDir,
                '--no-interaction',
                '--no-ansi',
                '--no-scripts',
                '-v',
                '--with-all-dependencies',
            ])
            ->willReturn(new StreamedResponse());

        $controller = new UpdateController(
            $recoveryManager,
            $this->createMock(ReleaseInfoProvider::class),
            $this->createMock(FlexMigrator::class),
            $responseGenerator,
        );
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = $controller->run($request);

        /** @var array{require: array<string, string>} $json */
        $json = json_decode((string) file_get_contents($tmpDir . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('dev-trunk as 6.5.9.9', $json['require']['shopware/core']);

        static::assertInstanceOf(StreamedResponse::class, $response);

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);
    }

    public function testResetConfig(): void
    {
        $recoveryManager = $this->createMock(RecoveryManager::class);

        $tmpDir = sys_get_temp_dir() . '/' . uniqid('test', true);
        $fs = new Filesystem();
        $fs->mkdir($tmpDir . '/vendor/symfony/flex/src/');
        $optionFile = $tmpDir . '/vendor/symfony/flex/src/Options.php';
        copy(__DIR__ . '/../_fixtures/Options.php', $optionFile);

        $recoveryManager->method('getShopwareLocation')->willReturn($tmpDir);
        $recoveryManager->method('getCurrentShopwareVersion')->willReturn('6.4.17.0');
        $recoveryManager->method('getPHPBinary')->willReturn('/usr/bin/php');
        $recoveryManager->method('getBinary')->willReturn('/var/www/shopware-installer.phar.php');

        $responseGenerator = $this->createMock(StreamedCommandResponseGenerator::class);
        $responseGenerator
            ->expects(static::once())
            ->method('runJSON')
            ->with([
                '/usr/bin/php',
                '-dmemory_limit=1G',
                '/var/www/shopware-installer.phar.php',
                '-d',
                $tmpDir,
                'symfony:recipes:install',
                '--force',
                '--reset',
                '--no-interaction',
                '--no-ansi',
                '-v',
            ])
            ->willReturn(new StreamedResponse());

        $controller = new UpdateController(
            $recoveryManager,
            $this->createMock(ReleaseInfoProvider::class),
            $this->createMock(FlexMigrator::class),
            $responseGenerator,
        );
        $controller->setContainer($this->getContainer());

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $response = $controller->resetConfig($request);

        $optionContent = (string) file_get_contents($optionFile);
        static::assertStringNotContainsString(', $file), false);', $optionContent);
        static::assertStringNotContainsString(', $name), false);', $optionContent);

        static::assertInstanceOf(StreamedResponse::class, $response);
    }

    public function getReleaseInfoProvider(): ReleaseInfoProvider&MockObject
    {
        $releaseInfoProvider = $this->createMock(ReleaseInfoProvider::class);
        $releaseInfoProvider
            ->expects(static::once())
            ->method('fetchUpdateVersions')
            ->willReturn(['6.3.5.0', '6.4.18.0']);

        return $releaseInfoProvider;
    }

    private function getContainer(): ContainerInterface
    {
        $container = new Container();

        $router = $this->createMock(Router::class);
        $router->method('generate')->willReturnArgument(0);

        $container->set('router', $router);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnArgument(0);

        $container->set('twig', $twig);

        return $container;
    }
}
