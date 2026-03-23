<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Component;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Storefront\Framework\Component\ComponentPublisher;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber;
use Shopware\Storefront\Theme\ThemeAppLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;

/**
 * @internal
 */
class ComponentLifecyclePublishingTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $projectDir;

    private ComponentPublisher $componentPublisher;

    private FilesystemOperator $publicFilesystem;

    private LocalFilesystem $localFilesystem;

    /**
     * @var list<string>
     */
    private array $createdBundleRoots = [];

    /**
     * @var list<string>
     */
    private array $publishedBundleNames = [];

    protected function setUp(): void
    {
        $this->projectDir = (string) static::getContainer()->getParameter('kernel.project_dir');
        $this->componentPublisher = static::getContainer()->get(ComponentPublisher::class);
        $this->publicFilesystem = static::getContainer()->get('shopware.filesystem.public');
        $this->localFilesystem = new LocalFilesystem();
    }

    protected function tearDown(): void
    {
        foreach (array_unique($this->publishedBundleNames) as $bundleName) {
            $this->componentPublisher->unpublish($bundleName);
        }

        foreach ($this->createdBundleRoots as $bundleRoot) {
            $this->localFilesystem->remove($bundleRoot);
        }
    }

    public function testPluginActivationPublishesComponentsAndUninstallUnpublishesThem(): void
    {
        $bundleName = 'LifecyclePlugin_' . substr(md5((string) microtime(true)), 0, 8);
        $bundleRoot = $this->createBundleWithComponentBuild($bundleName);

        $context = Context::createDefaultContext();
        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())->method('handleThemeInstallOrUpdate');
        $themeLifecycleHandler->expects($this->exactly(2))->method('refreshAllActiveThemeImportMaps');

        $subscriber = new PluginLifecycleSubscriber(
            static::getContainer()->get(StorefrontPluginRegistry::class),
            $this->projectDir,
            static::getContainer()->get(StorefrontPluginConfigurationFactory::class),
            $themeLifecycleHandler,
            $this->createMock(ThemeLifecycleService::class),
            $this->componentPublisher,
        );

        $plugin = new PluginEntity();
        $plugin->setName($bundleName);
        $plugin->setPath($bundleRoot);
        $plugin->setBaseClass(ComponentLifecycleTestPlugin::class);

        $subscriber->pluginPostActivate(new PluginPostActivateEvent(
            $plugin,
            new ActivateContext(
                $this->createMock(Plugin::class),
                $context,
                '1.0.0',
                '1.0.0',
                $this->createMock(MigrationCollection::class),
            ),
        ));

        $this->publishedBundleNames[] = $bundleName;
        $this->assertBundleIsPublished($bundleName);

        $uninstallContext = $this->createMock(UninstallContext::class);
        $uninstallContext->method('getContext')->willReturn($context);

        $event = $this->createMock(PluginPreUninstallEvent::class);
        $event->method('getPlugin')->willReturn($plugin);
        $event->method('getContext')->willReturn($uninstallContext);

        $subscriber->pluginDeactivateAndUninstall($event);
        $this->assertBundleIsUnpublished($bundleName);
    }

    public function testAppActivationPublishesComponentsAndDeactivationUnpublishesThem(): void
    {
        $bundleName = 'LifecycleApp_' . substr(md5((string) microtime(true)), 0, 8);
        $bundleRoot = $this->createBundleWithComponentBuild($bundleName);

        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $themeLifecycleHandler->expects($this->once())->method('handleThemeInstallOrUpdate');
        $themeLifecycleHandler->expects($this->once())->method('handleThemeUninstall');
        $themeLifecycleHandler->expects($this->exactly(2))->method('refreshAllActiveThemeImportMaps');

        $themeRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $themeRegistry->method('getConfigurations')->willReturnOnConsecutiveCalls(
            new StorefrontPluginConfigurationCollection(),
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration($bundleName),
            ]),
            new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration($bundleName),
            ]),
        );

        $configFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $configFactory->method('createFromApp')->willReturn(new StorefrontPluginConfiguration($bundleName));

        $sourceResolver = $this->createMock(SourceResolver::class);
        $sourceResolver->method('filesystemForApp')->willReturn(new Filesystem($bundleRoot));

        $handler = new ThemeAppLifecycleHandler(
            $themeRegistry,
            $configFactory,
            $themeLifecycleHandler,
            $this->componentPublisher,
            $sourceResolver,
        );

        $context = Context::createDefaultContext();
        $app = (new AppEntity())->assign([
            'name' => $bundleName,
            'path' => 'custom/apps/' . $bundleName,
            'active' => true,
        ]);

        $handler->handleAppActivationOrUpdate(new AppActivatedEvent($app, $context));

        $this->publishedBundleNames[] = $bundleName;
        $this->assertBundleIsPublished($bundleName);

        $handler->handleUninstall(new AppDeactivatedEvent(
            (new AppEntity())->assign(['name' => $bundleName]),
            $context,
        ));

        $this->assertBundleIsUnpublished($bundleName);
    }

    private function createBundleWithComponentBuild(string $bundleName): string
    {
        $bundleRoot = $this->projectDir . '/var/tests/component-lifecycle/' . $bundleName;
        $distRoot = $bundleRoot . '/Resources/app/storefront/dist-es/components';

        $this->localFilesystem->mkdir($distRoot . '/.vite');
        $this->localFilesystem->mkdir($distRoot . '/' . $bundleName);

        $manifest = [
            $bundleName . '/Button.js' => [
                'file' => $bundleName . '/Button.js',
                'name' => $bundleName . '/Button',
                'isEntry' => true,
            ],
        ];

        file_put_contents(
            $distRoot . '/.vite/manifest.json',
            (string) json_encode($manifest, \JSON_THROW_ON_ERROR),
        );
        file_put_contents(
            $distRoot . '/' . $bundleName . '/Button.js',
            'export default class Button {}',
        );

        $this->createdBundleRoots[] = $bundleRoot;

        return $bundleRoot;
    }

    private function assertBundleIsPublished(string $bundleName): void
    {
        static::assertTrue($this->publicFilesystem->fileExists('components/' . $bundleName . '/Button.js'));

        $manifest = $this->componentPublisher->readComponentManifest();
        static::assertArrayHasKey($bundleName . ':Button', $manifest);
        static::assertSame(
            '/components/' . $bundleName . '/Button.js',
            $manifest[$bundleName . ':Button']['js'] ?? null,
        );
    }

    private function assertBundleIsUnpublished(string $bundleName): void
    {
        static::assertFalse($this->publicFilesystem->fileExists('components/' . $bundleName . '/Button.js'));

        $manifest = $this->componentPublisher->readComponentManifest();
        static::assertArrayNotHasKey($bundleName . ':Button', $manifest);
    }
}

/**
 * @internal
 */
class ComponentLifecycleTestPlugin extends Plugin
{
}
