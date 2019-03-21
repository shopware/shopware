<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Test\DependencyInjection\fixtures\TestActionEventCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ActionEventCompilerPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register(BusinessEventRegistry::class, BusinessEventRegistry::class);

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
