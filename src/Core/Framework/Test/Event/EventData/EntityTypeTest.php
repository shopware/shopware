<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event\EventData;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Event\EventData\EntityType;

class EntityTypeTest extends TestCase
{
    public function testToArray()
    {
        $expected = [
            'type' => 'entity',
            'entity' => CustomerDefinition::getEntityName(),
        ];

        static::assertEquals($expected, (new EntityType(CustomerDefinition::class))->toArray());
    }
}
