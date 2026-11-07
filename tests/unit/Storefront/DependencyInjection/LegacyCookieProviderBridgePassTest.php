<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\DependencyInjection\LegacyCookieProviderBridgePass;
use Shopware\Storefront\Framework\Cookie\AppCookieProvider;
use Shopware\Storefront\Framework\Cookie\CookieProvider;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Shopware\Storefront\Framework\Cookie\LegacyCookieProviderBridge;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LegacyCookieProviderBridgePass::class)]
class LegacyCookieProviderBridgePassTest extends TestCase
{
    public function testBridgeIsRemovedWithoutThirdPartyDecorators(): void
    {
        $container = $this->createContainer();

        (new LegacyCookieProviderBridgePass())->process($container);

        static::assertFalse($container->hasDefinition(LegacyCookieProviderBridge::class));
    }

    public function testNothingHappensWhenTheBridgeIsAlreadyRemoved(): void
    {
        $container = $this->createContainer();
        $container->removeDefinition(LegacyCookieProviderBridge::class);
        $container->register('extension.cookie_provider', 'Extension\CookieProvider')
            ->setDecoratedService(CookieProviderInterface::class);

        (new LegacyCookieProviderBridgePass())->process($container);

        static::assertFalse($container->hasDefinition(LegacyCookieProviderBridge::class));
    }

    #[IgnoreDeprecations]
    public function testBridgeIsKeptAndDecoratorReportedAsDeprecation(): void
    {
        $container = $this->createContainer();
        $container->register('extension.cookie_provider', 'Extension\CookieProvider')
            ->setDecoratedService(CookieProviderInterface::class);

        $this->expectUserDeprecationMessageMatches('/The service "extension\.cookie_provider" \(Extension\\\\CookieProvider\) decorates or replaces/');

        (new LegacyCookieProviderBridgePass())->process($container);

        static::assertTrue($container->hasDefinition(LegacyCookieProviderBridge::class));
    }

    #[IgnoreDeprecations]
    public function testReplacedProviderIsReportedAsDeprecation(): void
    {
        $container = $this->createContainer();
        $container->getDefinition(CookieProviderInterface::class)->setClass('Extension\CookieProvider');

        $this->expectUserDeprecationMessageMatches('/The service "' . preg_quote(CookieProviderInterface::class, '/') . '" \(Extension\\\\CookieProvider\)/');

        (new LegacyCookieProviderBridgePass())->process($container);

        static::assertTrue($container->hasDefinition(LegacyCookieProviderBridge::class));
    }

    #[IgnoreDeprecations]
    public function testProviderReplacedByAnAliasIsReportedAsDeprecation(): void
    {
        $container = $this->createContainer();
        $container->register('extension.cookie_provider', 'Extension\CookieProvider');
        $container->setAlias(CookieProviderInterface::class, 'extension.cookie_provider');

        $this->expectUserDeprecationMessageMatches('/The service "' . preg_quote(CookieProviderInterface::class, '/') . '" \(Extension\\\\CookieProvider\)/');

        (new LegacyCookieProviderBridgePass())->process($container);

        static::assertTrue($container->hasDefinition(LegacyCookieProviderBridge::class));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(CookieProviderInterface::class, CookieProvider::class);
        $container->register(AppCookieProvider::class, AppCookieProvider::class)
            ->setDecoratedService(CookieProviderInterface::class)
            ->addArgument(new Reference('.inner'));
        $container->setDefinition(
            LegacyCookieProviderBridge::class,
            (new Definition(LegacyCookieProviderBridge::class))->addArgument(new Reference(CookieProviderInterface::class)),
        );

        return $container;
    }
}
