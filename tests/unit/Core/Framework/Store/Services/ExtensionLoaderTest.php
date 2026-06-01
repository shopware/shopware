<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\Framework\Store\Event\AppExtensionLoadedEvent;
use Shopware\Core\Framework\Store\Event\PluginExtensionLoadedEvent;
use Shopware\Core\Framework\Store\Services\ExtensionLoader;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Shopware\Core\Framework\Util\Exception\UtilXmlParsingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ExtensionLoader::class)]
class ExtensionLoaderTest extends TestCase
{
    public function testLoadFromPluginCollectionContinuesOnError(): void
    {
        $configurationService = $this->createMock(ConfigurationService::class);
        $configurationService
            ->method('checkConfiguration')
            ->willReturnCallback(static function (string $domain): bool {
                // Throw exception for the broken plugin
                if ($domain === 'BrokenPlugin.config') {
                    throw new UtilXmlParsingException('/path/to/config.xml', 'Invalid XML');
                }

                return true;
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to load plugin extension data',
                static::callback(static function (array $context): bool {
                    return $context['plugin'] === 'BrokenPlugin'
                        && str_contains($context['exception'], 'Invalid XML');
                })
            );

        $loader = $this->createLoader(new EventDispatcher(), $configurationService, $logger);

        $plugins = new PluginCollection([
            $this->createPlugin('WorkingPlugin'),
            $this->createPlugin('BrokenPlugin'),
            $this->createPlugin('AnotherWorkingPlugin'),
        ]);

        $context = Context::createDefaultContext();
        $extensions = $loader->loadFromPluginCollection($context, $plugins);

        // Should have 2 extensions (WorkingPlugin and AnotherWorkingPlugin)
        // BrokenPlugin should be skipped due to error
        static::assertCount(2, $extensions);
        static::assertTrue($extensions->has('WorkingPlugin'));
        static::assertTrue($extensions->has('AnotherWorkingPlugin'));
        static::assertFalse($extensions->has('BrokenPlugin'));
    }

    public function testLoadFromPluginCollectionLoadsAllPluginsWhenNoErrors(): void
    {
        $configurationService = $this->createMock(ConfigurationService::class);
        $configurationService->method('checkConfiguration')->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $loader = $this->createLoader(new EventDispatcher(), $configurationService, $logger);

        $plugins = new PluginCollection([
            $this->createPlugin('Plugin1'),
            $this->createPlugin('Plugin2'),
            $this->createPlugin('Plugin3'),
        ]);

        $context = Context::createDefaultContext();
        $extensions = $loader->loadFromPluginCollection($context, $plugins);

        static::assertCount(3, $extensions);
        static::assertTrue($extensions->has('Plugin1'));
        static::assertTrue($extensions->has('Plugin2'));
        static::assertTrue($extensions->has('Plugin3'));

        foreach ($extensions as $extension) {
            static::assertSame(ExtensionStruct::EXTENSION_TYPE_PLUGIN, $extension->getType());
            static::assertTrue($extension->isConfigurable());
        }
    }

    #[TestDox('loadFromPluginCollection dispatches PluginExtensionLoadedEvent carrying the plugin and context')]
    public function testLoadFromPluginDispatchesEventWithPluginAndContext(): void
    {
        $captured = null;
        $context = Context::createDefaultContext();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            PluginExtensionLoadedEvent::class,
            static function (PluginExtensionLoadedEvent $event) use (&$captured): void {
                $captured = $event;
            }
        );

        $this->createLoader($dispatcher)->loadFromPluginCollection(
            $context,
            new PluginCollection([$this->createPlugin('SomePlugin')])
        );

        static::assertInstanceOf(PluginExtensionLoadedEvent::class, $captured);
        static::assertSame('SomePlugin', $captured->plugin->getName());
        static::assertSame($context, $captured->context);
    }

    #[TestDox('A plugin is flagged as theme when a PluginExtensionLoadedEvent listener sets it')]
    public function testLoadFromPluginMarksThemeWhenListenerFlagsIt(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            PluginExtensionLoadedEvent::class,
            static fn (PluginExtensionLoadedEvent $event) => $event->isTheme = true
        );

        $extensions = $this->createLoader($dispatcher)->loadFromPluginCollection(
            Context::createDefaultContext(),
            new PluginCollection([$this->createPlugin('ThemePlugin')])
        );

        $extension = $extensions->get('ThemePlugin');
        static::assertNotNull($extension);
        static::assertTrue($extension->isTheme());
    }

    #[TestDox('A plugin is not a theme when no listener flags the event')]
    public function testLoadFromPluginIsNotThemeWithoutListener(): void
    {
        $extensions = $this->createLoader(new EventDispatcher())->loadFromPluginCollection(
            Context::createDefaultContext(),
            new PluginCollection([$this->createPlugin('PlainPlugin')])
        );

        $extension = $extensions->get('PlainPlugin');
        static::assertNotNull($extension);
        static::assertFalse($extension->isTheme());
    }

    #[TestDox('An app is flagged as theme when an AppExtensionLoadedEvent listener sets it')]
    public function testLoadFromAppMarksThemeWhenListenerFlagsIt(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            AppExtensionLoadedEvent::class,
            static fn (AppExtensionLoadedEvent $event) => $event->isTheme = true
        );

        $extensions = $this->createLoader($dispatcher)->loadFromAppCollection(
            Context::createDefaultContext(),
            new AppCollection([$this->createApp('ThemeApp')])
        );

        $extension = $extensions->get('ThemeApp');
        static::assertNotNull($extension);
        static::assertTrue($extension->isTheme());
    }

    #[TestDox('An app is not a theme when no listener flags the event')]
    public function testLoadFromAppIsNotThemeWithoutListener(): void
    {
        $extensions = $this->createLoader(new EventDispatcher())->loadFromAppCollection(
            Context::createDefaultContext(),
            new AppCollection([$this->createApp('PlainApp')])
        );

        $extension = $extensions->get('PlainApp');
        static::assertNotNull($extension);
        static::assertFalse($extension->isTheme());
    }

    private function createLoader(
        EventDispatcherInterface $eventDispatcher,
        ?ConfigurationService $configurationService = null,
        ?LoggerInterface $logger = null,
    ): ExtensionLoader {
        return new ExtensionLoader(
            $this->createMock(AppLoader::class),
            $this->createMock(SourceResolver::class),
            $configurationService ?? $this->createMock(ConfigurationService::class),
            $this->createMock(LocaleProvider::class),
            $this->createMock(LanguageLocaleCodeProvider::class),
            StaticInAppPurchaseFactory::createWithFeatures(),
            $logger ?? $this->createMock(LoggerInterface::class),
            $eventDispatcher,
        );
    }

    private function createPlugin(string $name): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setUniqueIdentifier($name);
        $plugin->assign([
            'id' => $name,
            'name' => $name,
            'baseClass' => 'NonExistentClass\\' . $name,
            'version' => '1.0.0',
            'active' => true,
            'managedByComposer' => false,
            'path' => 'custom/plugins/' . $name,
            'author' => 'Test Author',
        ]);
        $plugin->setTranslated([
            'label' => $name . ' Label',
            'description' => $name . ' Description',
        ]);

        return $plugin;
    }

    private function createApp(string $name): AppEntity
    {
        $app = new AppEntity();
        $app->setUniqueIdentifier($name);
        $app->assign([
            'id' => Uuid::randomHex(),
            'name' => $name,
            'version' => '1.0.0',
            'active' => true,
            'configurable' => false,
            'allowDisable' => true,
        ]);
        $app->setTranslated([
            'label' => $name . ' Label',
            'description' => $name . ' Description',
        ]);

        return $app;
    }
}
