<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class BusinessEventRegistryTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGetEvents(): void
    {
        $expectedData = [
            'test.event' => [
                'customer' => [
                    'type' => 'entity',
                    'entity' => 'customer',
                ],
                'orders' => [
                    'type' => 'collection',
                    'entity' => 'order',
                ],
            ],
            'test.event.1' => [
                'myBool' => [
                    'type' => 'bool',
                ],
            ],
            'test.event.2' => [],
        ];

        $rawData = [
            'test.event' => [
                'customer' => [
                    'type' => 'entity',
                    'entityClass' => CustomerDefinition::class,
                ],
                'orders' => [
                    'type' => 'collection',
                    'entityClass' => OrderDefinition::class,
                ],
            ],
            'test.event.1' => [
                'myBool' => [
                    'type' => 'bool',
                ],
            ],
            'test.event.2' => [],
        ];

        $registry = new BusinessEventRegistry($this->getContainer()->get(DefinitionInstanceRegistry::class));
        $registry->addMultiple($rawData);
        $result = $registry->getEvents();

        static::assertEquals($expectedData, $result);
    }

    public function testGetEventNames(): void
    {
        $registry = new BusinessEventRegistry($this->getContainer()->get(DefinitionInstanceRegistry::class));
        $registry->addMultiple([
            'test.event' => [
                'customer' => [
                    'type' => 'entity',
                    'entityClass' => CustomerDefinition::class,
                ],
                'orders' => [
                    'type' => 'collection',
                    'entityClass' => OrderDefinition::class,
                ],
            ],
            'test.event.1' => [
                'myBool' => [
                    'type' => 'bool',
                ],
            ],
            'test.event.2' => [],
        ]);

        $expected = [
            'test.event',
            'test.event.1',
            'test.event.2',
        ];

        $result = $registry->getEventNames();

        static::assertEquals($expected, $result);
    }

    public function testGetAvailableDataByEvent(): void
    {
        $expectedData = [
            'test.event' => [
                'customer' => [
                    'type' => 'entity',
                    'entity' => 'customer',
                ],
                'orders' => [
                    'type' => 'collection',
                    'entity' => 'order',
                ],
            ],
            'test.event.1' => [
                'myBool' => [
                    'type' => 'bool',
                ],
            ],
            'test.event.2' => [],
        ];

        $rawData = [
            'test.event' => [
                'customer' => [
                    'type' => 'entity',
                    'entityClass' => CustomerDefinition::class,
                ],
                'orders' => [
                    'type' => 'collection',
                    'entityClass' => OrderDefinition::class,
                ],
            ],
            'test.event.1' => [
                'myBool' => [
                    'type' => 'bool',
                ],
            ],
            'test.event.2' => [],
        ];

        $registry = new BusinessEventRegistry($this->getContainer()->get(DefinitionInstanceRegistry::class));
        $registry->addMultiple($rawData);

        static::assertEquals($expectedData['test.event'], $registry->getAvailableDataByEvent('test.event'));
        static::assertEquals($expectedData['test.event.1'], $registry->getAvailableDataByEvent('test.event.1'));
        static::assertEquals($expectedData['test.event.2'], $registry->getAvailableDataByEvent('test.event.2'));
    }
}
