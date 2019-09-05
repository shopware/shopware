<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Test\DependencyInjection\fixtures\TestActionEventCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

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
            'shopware.global_business_event' => [],
            'test.event' => [
                'customer' => [
                    'type' => 'entity',
                    'entity' => 'customer',
                ],
                'order' => [
                    'type' => 'entity',
                    'entity' => 'order',
                ],
            ],
        ];

        static::assertEquals($expected, $registry->getEvents());
    }
}
