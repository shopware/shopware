<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;

/**
 * @internal
 */
#[CoversClass(EventDataCollection::class)]
class EventDataCollectionTest extends TestCase
{
    public function testToArray(): void
    {
        $collection = (new EventDataCollection())
            ->add('customer', new EntityType(CustomerDefinition::class))
            ->add('myBool', new ScalarValueType(ScalarValueType::TYPE_BOOL))
        ;

        $expected = [
            'customer' => [
                'type' => 'entity',
                'entityClass' => CustomerDefinition::class,
                'entityName' => 'customer',
            ],
            'myBool' => [
                'type' => 'bool',
            ],
        ];

        static::assertEquals($expected, $collection->toArray());
    }
}
