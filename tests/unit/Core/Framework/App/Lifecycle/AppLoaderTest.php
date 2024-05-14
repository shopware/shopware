<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Manifest\Xml\Setup\Setup;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

/**
 * @internal
 */
#[CoversClass(AppLoader::class)]
#[CoversClass(AbstractAppLoader::class)]
class AppLoaderTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $packages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packages = InstalledVersions::getAllRawData()[0];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // @phpstan-ignore-next-line
        InstalledVersions::reload($this->packages);
    }

    public function testGetConfigWhenNotExists(): void
    {
        $appLoader = $this->getAppLoader();

        $appEntity = new AppEntity();
        $appEntity->setPath('non-existing');

        static::assertNull($appLoader->getConfiguration($appEntity));
    }

    public function testGetConfig(): void
    {
        $appLoader = $this->getAppLoader();

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/');

        static::assertNotNull($appLoader->getConfiguration($appEntity));
    }

    public function testGetCMSNotExistent(): void
    {
        $appLoader = $this->getAppLoader();

        $appEntity = new AppEntity();
        $appEntity->setPath('non-existing');

        static::assertNull($appLoader->getCmsExtensions($appEntity));
    }

    public function testGetCMS(): void
    {
        $appLoader = $this->getAppLoader();

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/');

        static::assertNotNull($appLoader->getCmsExtensions($appEntity));
    }

    public function testGetSnippets(): void
    {
        $expectedSnippet = [];
        $expectedSnippet['en-GB'] = file_get_contents(__DIR__ . '/../_fixtures/Resources/app/administration/snippet/en-GB.json');

        $appLoader = $this->getAppLoader();

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/');

        $snippets = $appLoader->getSnippets($appEntity);
        static::assertEquals($expectedSnippet, $snippets);
    }

    public function testSnippetsMissing(): void
    {
        $appLoader = $this->getAppLoader();

        $appEntity = new AppEntity();
        $appEntity->setPath('non-existing');

        static::assertSame([], $appLoader->getSnippets($appEntity));
    }

    public function testLoadAppByComposer(): void
    {
        $packages = InstalledVersions::getAllRawData();

        $modified = $packages[0];
        static::assertIsArray($modified);
        $modified['versions'] = [
            // Points to path that does not exist
            'swag/app' => [
                'dev_requirement' => false,
                'type' => AppLoader::COMPOSER_TYPE,
                'install_path' => __DIR__ . '/../_fixtures/',
            ],
        ];

        InstalledVersions::reload($modified);

        $appLoader = $this->getAppLoader();

        $apps = $appLoader->load();
        static::assertCount(1, $apps);
        static::assertArrayHasKey('test', $apps);

        $app = $apps['test'];

        static::assertTrue($app->isManagedByComposer());

        static::assertEquals('test', $app->getMetadata()->getName());
        static::assertEquals('1.0.0', $app->getMetadata()->getVersion());

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('App test is managed by Composer and cannot be deleted');
        $appLoader->deleteApp('test');
    }

    public function testLoadAppByComposerWithInvalidAppManifest(): void
    {
        $packages = InstalledVersions::getAllRawData();
        $modified = $packages[0];
        static::assertIsArray($modified);

        $modified['versions'] = [
            'swag/invalidManifestApp' => [
                'dev_requirement' => false,
                'type' => AppLoader::COMPOSER_TYPE,
                'install_path' => __DIR__ . '/_fixtures/invalidManifestApp',
            ],
        ];

        InstalledVersions::reload($modified);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(static::once())->method('error');

        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader(),
            $loggerMock
        );

        $appLoader->load();
    }

    public function testGetFlowActions(): void
    {
        $appLoader = $this->getAppLoader();

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/');

        $flowActions = $appLoader->getFlowActions($appEntity);
        static::assertNotNull($flowActions);
        static::assertNotNull($flowActions->getActions());
    }

    public function testGetFlowActionsWithFileNotExist(): void
    {
        $appLoader = $this->getAppLoader();

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/flow/');

        $flowActions = $appLoader->getFlowActions($appEntity);
        static::assertNull($flowActions);
    }

    public function testGetFlowEvents(): void
    {
        $appLoader = $this->getAppLoader();

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/');

        $expected = [
            'name' => 'swag.before.open_the_doors',
            'aware' => ['customerAware'],
        ];

        $events = $appLoader->getFlowEvents($appEntity);
        static::assertNotNull($events);
        static::assertNotNull($events->getCustomEvents());
        $customEvents = $events->getCustomEvents();
        $events = $customEvents->getCustomEvents();
        static::assertEquals($expected, $events[0]->toArray('en-GB'));
    }

    public function testGetFlowEventsWithFileNotExist(): void
    {
        $appLoader = $this->getAppLoader();

        $appEntity = new AppEntity();
        $appEntity->setPath(__DIR__ . '/../_fixtures/flow/');

        $events = $appLoader->getFlowEvents($appEntity);
        static::assertNull($events);
    }

    public function testLoadShouldLoadOnlyValidPlugin(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(static::exactly(2))->method('error');

        $appLoader = new AppLoader(
            __DIR__ . '/_fixtures/appDirValidationTest',
            __DIR__,
            new ConfigReader(),
            $loggerMock
        );

        $result = $appLoader->load();

        static::assertCount(2, $result);
        static::assertArrayHasKey('ValidManifestApp', $result);
        static::assertArrayHasKey('ValidAppWithLocalManifest', $result);
    }

    public function testLoadLocalManifest(): void
    {
        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects(static::exactly(2))->method('error');

        $appLoader = new AppLoader(
            __DIR__ . '/_fixtures/appDirValidationTest',
            __DIR__,
            new ConfigReader(),
            $loggerMock
        );

        $result = $appLoader->load();

        static::assertArrayHasKey('ValidAppWithLocalManifest', $result);

        $localManifestApp = $result['ValidAppWithLocalManifest'];

        static::assertSame($localManifestApp->getMetadata()->getPrivacy(), 'https://overrided.com/privacy');
        static::assertInstanceOf(Setup::class, $setup = $localManifestApp->getSetup());
        static::assertSame($setup->getRegistrationUrl(), 'https://overrided.com/auth');
        static::assertSame($setup->getSecret(), 'APP_SECRET');
    }

    private function getAppLoader(): AppLoader
    {
        return new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader(),
            new NullLogger()
        );
    }
}
