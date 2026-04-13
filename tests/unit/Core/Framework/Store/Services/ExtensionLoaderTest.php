<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\Framework\Store\Services\ExtensionLoader;
use Shopware\Core\Framework\Store\Struct\ExtensionStruct;
use Shopware\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Shopware\Core\Framework\Util\Exception\UtilXmlParsingException;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;

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

        $loader = new ExtensionLoader(
            null,
            $this->createMock(AppLoader::class),
            $this->createMock(SourceResolver::class),
            $configurationService,
            $this->createMock(LocaleProvider::class),
            $this->createMock(LanguageLocaleCodeProvider::class),
            StaticInAppPurchaseFactory::createWithFeatures(),
            $logger,
        );

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

        $loader = new ExtensionLoader(
            null,
            $this->createMock(AppLoader::class),
            $this->createMock(SourceResolver::class),
            $configurationService,
            $this->createMock(LocaleProvider::class),
            $this->createMock(LanguageLocaleCodeProvider::class),
            StaticInAppPurchaseFactory::createWithFeatures(),
            $logger,
        );

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
}
