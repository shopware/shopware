<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Event\EventData;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Event\EventData\EntityCollectionType;

/**
 * @internal
 */
class EntityCollectionTypeTest extends TestCase
{
    public function testToArray(): void
    {
        $expected = [
            'type' => 'collection',
            'entityClass' => CustomerDefinition::class,
        ];

        static::assertEquals($expected, (new EntityCollectionType(CustomerDefinition::class))->toArray());
    }
}
