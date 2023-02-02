<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Test\DependencyInjection\fixtures\TestActionEventCompilerPass;
use Shopware\Core\Framework\Test\DependencyInjection\fixtures\TestEvent;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
class ActionEventCompilerPassTest extends TestCase
{
    public function testProcess(): void
    {
        $container = new ContainerBuilder();

        $container->register(CustomerDefinition::class, CustomerDefinition::class);
        $container->register(OrderDefinition::class, OrderDefinition::class);

        $container->register(DefinitionInstanceRegistry::class, DefinitionInstanceRegistry::class)
            ->addArgument(new Reference('service_container'))
            ->addArgument([])
            ->addArgument([]);

        $container->register(BusinessEventRegistry::class, BusinessEventRegistry::class)
            ->addArgument(new Reference(DefinitionInstanceRegistry::class));

        $pass = new TestActionEventCompilerPass();
        $pass->process($container);

        $registry = $container->get(BusinessEventRegistry::class);

        $expected = [
            TestEvent::class,
        ];

        static::assertEquals($expected, $registry->getClasses());
    }
}
