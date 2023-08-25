<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Lifecycle\AppLoader
 * @covers \Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader
 */
class AppLoaderTest extends TestCase
{
    /**
     * @var array<mixed>
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
        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader()
        );

        $appEntity = new AppEntity();
        $appEntity->setPath('non-existing');

        static::assertNull($appLoader->getConfiguration($appEntity));
    }

    public function testGetConfig(): void
    {
        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader()
        );

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/');

        static::assertNotNull($appLoader->getConfiguration($appEntity));
    }

    public function testGetCMSNotExistent(): void
    {
        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader()
        );

        $appEntity = new AppEntity();
        $appEntity->setPath('non-existing');

        static::assertNull($appLoader->getCmsExtensions($appEntity));
    }

    public function testGetCMS(): void
    {
        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader()
        );

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/');

        static::assertNotNull($appLoader->getCmsExtensions($appEntity));
    }

    public function testGetSnippets(): void
    {
        $expectedSnippet = [];
        $expectedSnippet['en-GB'] = file_get_contents(__DIR__ . '/../_fixtures/Resources/app/administration/snippet/en-GB.json');

        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader()
        );

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/');

        $snippets = $appLoader->getSnippets($appEntity);
        static::assertEquals($expectedSnippet, $snippets);
    }

    public function testSnippetsMissing(): void
    {
        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader()
        );

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
            // Points to path that does not exists
            'swag/app' => [
                'dev_requirement' => false,
                'type' => AppLoader::COMPOSER_TYPE,
                'install_path' => __DIR__ . '/../_fixtures/',
            ],
        ];

        InstalledVersions::reload($modified);

        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader()
        );

        $apps = $appLoader->load();
        static::assertCount(1, $apps);
        static::assertArrayHasKey('test', $apps);

        $app = $apps['test'];

        static::assertTrue($app->isManagedByComposer());

        static::assertEquals('test', $app->getMetadata()->getName());
        static::assertEquals('1.0.0', $app->getMetadata()->getVersion());

        static::expectException(AppException::class);
        $appLoader->deleteApp('test');
    }

    public function testGetFlowActions(): void
    {
        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader()
        );

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/');

        $flowActions = $appLoader->getFlowActions($appEntity);
        static::assertNotNull($flowActions);
        static::assertNotNull($flowActions->getActions());
    }

    public function testGetFlowActionsWithFileNotExist(): void
    {
        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader()
        );

        $appEntity = new AppEntity();
        $appEntity->setPath('../_fixtures/flow/');

        $flowActions = $appLoader->getFlowActions($appEntity);
        static::assertNull($flowActions);
    }

    public function testGetFlowEvents(): void
    {
        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader()
        );

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
        $appLoader = new AppLoader(
            __DIR__,
            __DIR__,
            new ConfigReader()
        );

        $appEntity = new AppEntity();
        $appEntity->setPath(__DIR__ . '/../_fixtures/flow/');

        $events = $appLoader->getFlowEvents($appEntity);
        static::assertNull($events);
    }
}
