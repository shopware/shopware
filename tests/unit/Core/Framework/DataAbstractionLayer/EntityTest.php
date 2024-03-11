<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;

/**
 * @internal
 */
#[CoversClass(Entity::class)]
class EntityTest extends TestCase
{
    /**
     * @return \Generator<array{class-string<Entity>, string}>
     */
    public static function apiAliasDefaultsDataProvider(): iterable
    {
        yield [
            FooEntity::class,
            'foo',
        ];

        yield [
            FooBarEntity::class,
            'foo_bar',
        ];

        yield [
            FooBar::class,
            'foo_bar',
        ];

        yield [
            ProductEntityRelationEntity::class,
            'product_entity_relation',
        ];

        yield [
            ProductEntity::class,
            ProductDefinition::ENTITY_NAME,
        ];

        yield [
            ProductPriceEntity::class,
            ProductPriceDefinition::ENTITY_NAME,
        ];

        yield [
            SalesChannelDomainEntity::class,
            SalesChannelDomainDefinition::ENTITY_NAME,
        ];
    }

    #[DataProvider('apiAliasDefaultsDataProvider')]
    public function testApiAlias(string $class, string $expected): void
    {
        /** @var Entity $entity */
        $entity = new $class();

        static::assertSame($expected, $entity->getApiAlias());
    }

    public function testCustomApiAliasHasPrecedence(): void
    {
        $entity = new FooBarEntity();
        $entity->internalSetEntityData('custom_entity_name', new FieldVisibility([]));

        static::assertSame('custom_entity_name', $entity->getApiAlias());
    }
}

/**
 * @internal
 */
class FooEntity extends Entity
{
}

/**
 * @internal
 */
class FooBarEntity extends Entity
{
}

/**
 * @internal
 */
class FooBar extends Entity
{
}

/**
 * @internal
 */
class ProductEntityRelationEntity extends Entity
{
}
