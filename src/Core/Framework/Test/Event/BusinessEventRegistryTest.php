<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Event\BusinessEventRegistry;

class BusinessEventRegistryTest extends TestCase
{
    public function testGetEvents()
    {
        $data = [
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

        $registry = new BusinessEventRegistry();
        $registry->addMultiple($data);
        $result = $registry->getEvents();

        static::assertEquals($data, $result);
    }

    public function testGetEventNames()
    {
        $registry = new BusinessEventRegistry();
        $registry->addMultiple([
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
        ]);

        $expected = [
            'test.event',
            'test.event.1',
            'test.event.2',
        ];

        $result = $registry->getEventNames();

        static::assertEquals($expected, $result);
    }

    public function testGetAvailableDataByEvent()
    {
        $data = [
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

        $registry = new BusinessEventRegistry();
        $registry->addMultiple($data);

        static::assertEquals($data['test.event'], $registry->getAvailableDataByEvent('test.event'));
        static::assertEquals($data['test.event.1'], $registry->getAvailableDataByEvent('test.event.1'));
        static::assertEquals($data['test.event.2'], $registry->getAvailableDataByEvent('test.event.2'));
    }
}
