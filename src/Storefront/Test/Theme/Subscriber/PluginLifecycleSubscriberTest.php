<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Theme\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Event\PluginPreActivateEvent;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Test\Plugin\PluginTestsHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use SwagTest\SwagTest;

class PluginLifecycleSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PluginTestsHelper;

    public function setUp(): void
    {
        parent::setUp();
        $this->addTestPluginToKernel('SwagTest');
    }

    public function testThemeLifecycleIsNotCalledWhenDeactivatedUsingContextOnActivate(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(Plugin\PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        $event = new PluginPreActivateEvent(
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

        $subscriber->pluginActivate($event);
    }

    public function testThemeLifecycleIsNotCalledWhenDeactivatedUsingContextOnUpdate(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(Plugin\PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        $event = new Plugin\Event\PluginPreUpdateEvent(
            $this->getPlugin(),
            new Plugin\Context\UpdateContext(
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
