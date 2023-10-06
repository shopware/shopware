<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Test\Plugin\PluginTestsHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use SwagTest\SwagTest;

/**
 * @internal
 */
class PluginLifecycleSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PluginTestsHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addTestPluginToKernel(
            __DIR__ . '/../../../../Core/Framework/Test/Plugin/_fixture/plugins/SwagTest',
            'SwagTest'
        );
    }

    public function testDoesNotAddPluginStorefrontConfigurationToConfigurationCollectionIfItIsAddedAlready(): void
    {
        $context = Context::createDefaultContext();
        $event = new PluginPostActivateEvent(
            $this->getPlugin(),
            new ActivateContext(
                $this->createMock(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                $this->createMock(MigrationCollection::class)
            )
        );
        $storefrontPluginConfigMock = new StorefrontPluginConfiguration('SwagTest');
        // Plugin storefront config is already added here
        $storefrontPluginConfigCollection = new StorefrontPluginConfigurationCollection([$storefrontPluginConfigMock]);

        $pluginConfigurationFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $pluginConfigurationFactory->method('createFromBundle')->willReturn($storefrontPluginConfigMock);
        $storefrontPluginRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $storefrontPluginRegistry->method('getConfigurations')->willReturn($storefrontPluginConfigCollection);
        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects(static::once())->method('handleThemeInstallOrUpdate')->with(
            $storefrontPluginConfigMock,
            // This ensures the plugin storefront config is not added twice
            static::equalTo($storefrontPluginConfigCollection),
            $context,
        );

        $subscriber = new PluginLifecycleSubscriber(
            $storefrontPluginRegistry,
            __DIR__,
            $pluginConfigurationFactory,
            $handler,
            $this->createMock(ThemeLifecycleService::class)
        );

        $subscriber->pluginPostActivate($event);
    }

    public function testAddsThePluginStorefrontConfigurationToConfigurationCollectionIfItWasNotAddedAlready(): void
    {
        $context = Context::createDefaultContext();
        $event = new PluginPostActivateEvent(
            $this->getPlugin(),
            new ActivateContext(
                $this->createMock(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                $this->createMock(MigrationCollection::class)
            )
        );
        $storefrontPluginConfigMock = new StorefrontPluginConfiguration('SwagTest');
        // Plugin storefront config is not added here
        $storefrontPluginConfigCollection = new StorefrontPluginConfigurationCollection([]);

        $pluginConfigurationFactory = $this->createMock(AbstractStorefrontPluginConfigurationFactory::class);
        $pluginConfigurationFactory->method('createFromBundle')->willReturn($storefrontPluginConfigMock);
        $storefrontPluginRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $storefrontPluginRegistry->method('getConfigurations')->willReturn($storefrontPluginConfigCollection);
        $collectionWithPluginConfig = clone $storefrontPluginConfigCollection;
        $collectionWithPluginConfig->add($storefrontPluginConfigMock);
        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects(static::once())->method('handleThemeInstallOrUpdate')->with(
            $storefrontPluginConfigMock,
            // This ensures the plugin storefront config was added in the subscriber
            static::equalTo($collectionWithPluginConfig),
            $context,
        );

        $subscriber = new PluginLifecycleSubscriber(
            $storefrontPluginRegistry,
            __DIR__,
            $pluginConfigurationFactory,
            $handler,
            $this->createMock(ThemeLifecycleService::class)
        );

        $subscriber->pluginPostActivate($event);
    }

    public function testThemeLifecycleIsNotCalledWhenDeactivatedUsingContextOnActivate(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        $event = new PluginPostActivateEvent(
            $this->getPlugin(),
            new ActivateContext(
                $this->createMock(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                $this->createMock(MigrationCollection::class)
            )
        );

        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects(static::never())->method('handleThemeInstallOrUpdate');

        $subscriber = new PluginLifecycleSubscriber(
            $this->createMock(StorefrontPluginRegistry::class),
            __DIR__,
            $this->createMock(AbstractStorefrontPluginConfigurationFactory::class),
            $handler,
            $this->createMock(ThemeLifecycleService::class)
        );

        $subscriber->pluginPostActivate($event);
    }

    public function testThemeLifecycleIsNotCalledWhenDeactivatedUsingContextOnUpdate(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        $event = new PluginPreUpdateEvent(
            $this->getPlugin(),
            new UpdateContext(
                $this->createMock(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                $this->createMock(MigrationCollection::class),
                '1.0.1'
            )
        );

        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects(static::never())->method('handleThemeInstallOrUpdate');

        $subscriber = new PluginLifecycleSubscriber(
            $this->createMock(StorefrontPluginRegistry::class),
            __DIR__,
            $this->createMock(AbstractStorefrontPluginConfigurationFactory::class),
            $handler,
            $this->createMock(ThemeLifecycleService::class)
        );

        $subscriber->pluginUpdate($event);
    }

    private function getPlugin(): PluginEntity
    {
        return (new PluginEntity())
            ->assign([
                'path' => (new \ReflectionClass(SwagTest::class))->getFileName(),
                'baseClass' => SwagTest::class,
            ]);
    }
}
