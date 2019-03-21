<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event\EventData;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;

class EntityCollectionTypeTest extends TestCase
{
    public function testToArray()
    {
        $expected = [
            'type' => 'collection',
            'entity' => CustomerDefinition::getEntityName(),
        ];

        static::assertEquals($expected, (new EntityCollectionType(CustomerDefinition::class))->toArray());
    }
}
