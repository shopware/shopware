<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\FrameworkException;

/**
 * @internal
 */
#[CoversClass(EntityType::class)]
class EntityTypeTest extends TestCase
{
    public function testToArray(): void
    {
        $definition = CustomerDefinition::class;

        $expected = [
            'type' => 'entity',
            'entityClass' => CustomerDefinition::class,
            'entityName' => 'customer',
        ];

        static::assertEquals($expected, (new EntityType($definition))->toArray());
        static::assertEquals($expected, (new EntityType(new CustomerDefinition()))->toArray());
    }

    public function testConstructWithInvalidDefinitionClass(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);

        $this->expectException(FrameworkException::class);
        $this->expectExceptionMessage('Expected an instance of Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition or a class name that extends Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition');

        /* @phpstan-ignore-next-line */
        new EntityType(\stdClass::class);
    }
}
