<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Checkout;
use Shopware\Core\Checkout\DependencyInjection\CompilerPass\CartRedisCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Checkout::class)]
class CheckoutTest extends TestCase
{
    public function testBuildUsesRedisCompilerPass(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'foo');

        $checkout = new Checkout();
        $checkout->build($container);

        $cartRedisCompilerPass = \array_filter(
            $container->getCompiler()->getPassConfig()->getBeforeOptimizationPasses(),
            static fn (CompilerPassInterface $pass) => $pass instanceof CartRedisCompilerPass
        );

        static::assertCount(1, $cartRedisCompilerPass);
    }
}
