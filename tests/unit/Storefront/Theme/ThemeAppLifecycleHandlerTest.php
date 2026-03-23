<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Storefront\Framework\Component\ComponentPublisher;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeAppLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;

/**
 * @internal
 */
#[CoversClass(ThemeAppLifecycleHandler::class)]
class ThemeAppLifecycleHandlerTest extends TestCase
{
    public function testHandleUninstallRecompilesThemesForComponentOnlyAppWhenManifestChanged(): void
    {
        $themeRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $themeConfigFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $componentPublisher = $this->createMock(ComponentPublisher::class);
        $sourceResolver = $this->createMock(SourceResolver::class);

        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $configurationCollection = new StorefrontPluginConfigurationCollection([$config]);
        $themeRegistry
            ->method('getConfigurations')
            ->willReturn($configurationCollection);

        $componentPublisher
            ->expects($this->once())
            ->method('unpublish')
            ->with('ComponentTestApp')
            ->willReturn(true);

        $themeLifecycleHandler
            ->expects($this->once())
            ->method('handleThemeUninstall')
            ->with($config, $this->isInstanceOf(Context::class));

        $themeLifecycleHandler
            ->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with($this->isInstanceOf(Context::class), $this->isInstanceOf(StorefrontPluginConfigurationCollection::class));

        $handler = new ThemeAppLifecycleHandler(
            $themeRegistry,
            $themeConfigFactory,
            $themeLifecycleHandler,
            $componentPublisher,
            $sourceResolver,
        );

        $app = (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
        ]);

        $handler->handleUninstall(new AppDeactivatedEvent($app, Context::createDefaultContext()));
    }

    public function testHandleUninstallSkipsFallbackRecompileWhenManifestUnchanged(): void
    {
        $themeRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $themeConfigFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $componentPublisher = $this->createMock(ComponentPublisher::class);
        $sourceResolver = $this->createMock(SourceResolver::class);

        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $themeRegistry
            ->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection([$config]));

        $componentPublisher
            ->expects($this->once())
            ->method('unpublish')
            ->willReturn(false);

        $themeLifecycleHandler
            ->expects($this->once())
            ->method('handleThemeUninstall');

        $themeLifecycleHandler
            ->expects($this->never())
            ->method('refreshAllActiveThemeImportMaps');

        $handler = new ThemeAppLifecycleHandler(
            $themeRegistry,
            $themeConfigFactory,
            $themeLifecycleHandler,
            $componentPublisher,
            $sourceResolver,
        );

        $app = (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
        ]);

        $handler->handleUninstall(new AppDeactivatedEvent($app, Context::createDefaultContext()));
    }

    public function testHandleUninstallRecompilesThemesWhenConfigMissingAndManifestChanged(): void
    {
        $themeRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $themeConfigFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $componentPublisher = $this->createMock(ComponentPublisher::class);
        $sourceResolver = $this->createMock(SourceResolver::class);

        $themeRegistry
            ->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection());

        $componentPublisher
            ->expects($this->once())
            ->method('unpublish')
            ->with('ComponentTestApp')
            ->willReturn(true);

        $themeLifecycleHandler
            ->expects($this->never())
            ->method('handleThemeUninstall');

        $themeLifecycleHandler
            ->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with($this->isInstanceOf(Context::class), $this->isInstanceOf(StorefrontPluginConfigurationCollection::class));

        $handler = new ThemeAppLifecycleHandler(
            $themeRegistry,
            $themeConfigFactory,
            $themeLifecycleHandler,
            $componentPublisher,
            $sourceResolver,
        );

        $app = (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
        ]);

        $handler->handleUninstall(new AppDeactivatedEvent($app, Context::createDefaultContext()));
    }

    public function testHandleAppActivationOrUpdatePublishesBeforeThemeCompile(): void
    {
        $themeRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $themeConfigFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $componentPublisher = $this->createMock(ComponentPublisher::class);
        $sourceResolver = $this->createMock(SourceResolver::class);

        $themeRegistry
            ->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection());

        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $themeConfigFactory
            ->expects($this->once())
            ->method('createFromApp')
            ->willReturn($config);

        $sourceResolver
            ->method('filesystemForApp')
            ->willReturn(new Filesystem('/app/custom/apps/ComponentTestApp'));

        $calls = [];
        $componentPublisher
            ->expects($this->once())
            ->method('publishBundle')
            ->willReturnCallback(function () use (&$calls): bool {
                $calls[] = 'publish';

                return true;
            });

        $themeLifecycleHandler
            ->expects($this->once())
            ->method('handleThemeInstallOrUpdate')
            ->willReturnCallback(function () use (&$calls): void {
                static::assertSame(['publish'], $calls);
                $calls[] = 'compile';
            });
        $themeLifecycleHandler
            ->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps');

        $handler = new ThemeAppLifecycleHandler(
            $themeRegistry,
            $themeConfigFactory,
            $themeLifecycleHandler,
            $componentPublisher,
            $sourceResolver,
        );

        $app = (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
            'path' => 'custom/apps/ComponentTestApp',
            'active' => true,
        ]);

        $handler->handleAppActivationOrUpdate(new AppActivatedEvent($app, Context::createDefaultContext()));
        static::assertSame(['publish', 'compile'], $calls);
    }

    public function testHandleAppActivationOrUpdateRecompilesThemesForComponentOnlyApp(): void
    {
        $themeRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $themeConfigFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $componentPublisher = $this->createMock(ComponentPublisher::class);
        $sourceResolver = $this->createMock(SourceResolver::class);

        $configurationCollection = new StorefrontPluginConfigurationCollection();
        $themeRegistry
            ->method('getConfigurations')
            ->willReturn($configurationCollection);

        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $themeConfigFactory
            ->expects($this->once())
            ->method('createFromApp')
            ->willReturn($config);

        $sourceResolver
            ->method('filesystemForApp')
            ->willReturn(new Filesystem('/app/custom/apps/ComponentTestApp'));

        $componentPublisher
            ->expects($this->once())
            ->method('publishBundle')
            ->willReturn(true);

        $themeLifecycleHandler
            ->expects($this->once())
            ->method('handleThemeInstallOrUpdate');

        $themeLifecycleHandler
            ->expects($this->once())
            ->method('refreshAllActiveThemeImportMaps')
            ->with($this->isInstanceOf(Context::class), $this->isInstanceOf(StorefrontPluginConfigurationCollection::class));

        $handler = new ThemeAppLifecycleHandler(
            $themeRegistry,
            $themeConfigFactory,
            $themeLifecycleHandler,
            $componentPublisher,
            $sourceResolver,
        );

        $app = (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
            'path' => 'custom/apps/ComponentTestApp',
            'active' => true,
        ]);

        $handler->handleAppActivationOrUpdate(new AppActivatedEvent($app, Context::createDefaultContext()));
    }

    public function testHandleAppActivationOrUpdateSkipsFallbackRecompileWhenSkipStateIsSet(): void
    {
        $themeRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $themeConfigFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $componentPublisher = $this->createMock(ComponentPublisher::class);
        $sourceResolver = $this->createMock(SourceResolver::class);

        $themeRegistry
            ->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection());

        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $themeConfigFactory
            ->expects($this->once())
            ->method('createFromApp')
            ->willReturn($config);

        $sourceResolver
            ->method('filesystemForApp')
            ->willReturn(new Filesystem('/app/custom/apps/ComponentTestApp'));

        $componentPublisher
            ->expects($this->once())
            ->method('publishBundle')
            ->willReturn(true);

        $themeLifecycleHandler
            ->expects($this->once())
            ->method('handleThemeInstallOrUpdate');

        $themeLifecycleHandler
            ->expects($this->never())
            ->method('refreshAllActiveThemeImportMaps');

        $handler = new ThemeAppLifecycleHandler(
            $themeRegistry,
            $themeConfigFactory,
            $themeLifecycleHandler,
            $componentPublisher,
            $sourceResolver,
        );

        $app = (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
            'path' => 'custom/apps/ComponentTestApp',
            'active' => true,
        ]);

        $context = Context::createDefaultContext();
        $context->addState(ThemeLifecycleHandler::STATE_SKIP_THEME_COMPILATION);

        $handler->handleAppActivationOrUpdate(new AppActivatedEvent($app, $context));
    }

    public function testHandleAppActivationOrUpdatePublishesComponentsFromProjectRelativePath(): void
    {
        $themeRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $themeConfigFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $componentPublisher = $this->createMock(ComponentPublisher::class);
        $sourceResolver = $this->createMock(SourceResolver::class);

        $themeRegistry
            ->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection());

        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $themeConfigFactory
            ->expects($this->once())
            ->method('createFromApp')
            ->with('ComponentTestApp', 'custom/apps/ComponentTestApp')
            ->willReturn($config);

        $themeLifecycleHandler
            ->expects($this->once())
            ->method('handleThemeInstallOrUpdate');
        $themeLifecycleHandler
            ->expects($this->never())
            ->method('refreshAllActiveThemeImportMaps');

        $componentPublisher
            ->expects($this->once())
            ->method('publishBundle')
            ->with('/app/custom/apps/ComponentTestApp', 'ComponentTestApp')
            ->willReturn(false);

        $sourceResolver
            ->expects($this->once())
            ->method('filesystemForApp')
            ->willReturn(new Filesystem('/app/custom/apps/ComponentTestApp'));

        $handler = new ThemeAppLifecycleHandler(
            $themeRegistry,
            $themeConfigFactory,
            $themeLifecycleHandler,
            $componentPublisher,
            $sourceResolver,
        );

        $app = (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
            'path' => 'custom/apps/ComponentTestApp',
            'active' => true,
        ]);

        $handler->handleAppActivationOrUpdate(new AppActivatedEvent($app, Context::createDefaultContext()));
    }

    public function testHandleAppActivationOrUpdateKeepsAbsolutePathWhenPublishingComponents(): void
    {
        $themeRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $themeConfigFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $componentPublisher = $this->createMock(ComponentPublisher::class);
        $sourceResolver = $this->createMock(SourceResolver::class);

        $config = new StorefrontPluginConfiguration('ComponentTestApp');
        $themeRegistry
            ->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection([$config]));

        $themeConfigFactory->expects($this->never())->method('createFromApp');
        $themeLifecycleHandler
            ->expects($this->once())
            ->method('handleThemeInstallOrUpdate');
        $themeLifecycleHandler
            ->expects($this->never())
            ->method('refreshAllActiveThemeImportMaps');

        $componentPublisher
            ->expects($this->once())
            ->method('publishBundle')
            ->with('/app/custom/apps/ComponentTestApp', 'ComponentTestApp')
            ->willReturn(false);

        $sourceResolver
            ->expects($this->once())
            ->method('filesystemForApp')
            ->willReturn(new Filesystem('/app/custom/apps/ComponentTestApp'));

        $handler = new ThemeAppLifecycleHandler(
            $themeRegistry,
            $themeConfigFactory,
            $themeLifecycleHandler,
            $componentPublisher,
            $sourceResolver,
        );

        $app = (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
            'path' => '/app/custom/apps/ComponentTestApp',
            'active' => true,
        ]);

        $handler->handleAppActivationOrUpdate(new AppActivatedEvent($app, Context::createDefaultContext()));
    }

    public function testHandleAppActivationOrUpdatePublishesComponentsFromRemoteZipFilesystem(): void
    {
        $themeRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $themeConfigFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $themeLifecycleHandler = $this->createMock(ThemeLifecycleHandler::class);
        $componentPublisher = $this->createMock(ComponentPublisher::class);
        $sourceResolver = $this->createMock(SourceResolver::class);

        $themeRegistry
            ->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection([
                new StorefrontPluginConfiguration('ComponentTestApp'),
            ]));

        $themeConfigFactory->expects($this->never())->method('createFromApp');
        $themeLifecycleHandler
            ->expects($this->once())
            ->method('handleThemeInstallOrUpdate');
        $themeLifecycleHandler
            ->expects($this->never())
            ->method('refreshAllActiveThemeImportMaps');

        $componentPublisher
            ->expects($this->once())
            ->method('publishBundle')
            ->with('/tmp/apps/ComponentTestApp', 'ComponentTestApp')
            ->willReturn(false);

        $sourceResolver
            ->expects($this->once())
            ->method('filesystemForApp')
            ->willReturn(new Filesystem('/tmp/apps/ComponentTestApp'));

        $handler = new ThemeAppLifecycleHandler(
            $themeRegistry,
            $themeConfigFactory,
            $themeLifecycleHandler,
            $componentPublisher,
            $sourceResolver,
        );

        $app = (new AppEntity())->assign([
            'name' => 'ComponentTestApp',
            'path' => 'https://example.invalid/ComponentTestApp.zip',
            'sourceType' => 'remote-zip',
            'active' => true,
        ]);

        $handler->handleAppActivationOrUpdate(new AppActivatedEvent($app, Context::createDefaultContext()));
    }
}
