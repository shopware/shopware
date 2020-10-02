<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer {
    use PHPUnit\Framework\TestCase;
    use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
    use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
    use Shopware\Core\Content\Product\ProductDefinition;
    use Shopware\Core\Content\Product\ProductEntity;
    use Shopware\Core\Framework\DataAbstractionLayer\Entity;
    use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
    use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
    use Test\Foo\FooBar;
    use Test\Foo\FooBarEntity;
    use Test\Foo\FooEntity;
    use Test\Foo\ProductEntityRelationEntity;

    class EntityTest extends TestCase
    {
        public function apiAliasDefaultsDataProvider(): iterable
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

        /**
         * @dataProvider apiAliasDefaultsDataProvider
         */
        public function testApiAlias(string $class, string $expected): void
        {
            /** @var Entity $entity */
            $entity = new $class();

            static::assertSame($expected, $entity->getApiAlias());
        }

        public function testCustomApiAliasHasPrecedence(): void
        {
            $entity = new FooBarEntity();
            $entity->internalSetEntityName('custom_entity_name');

            static::assertSame('custom_entity_name', $entity->getApiAlias());
        }
    }
}

namespace Test\Foo {
    use Shopware\Core\Framework\DataAbstractionLayer\Entity;

    class FooEntity extends Entity
    {
    }

    class FooBarEntity extends Entity
    {
    }

    class FooBar extends Entity
    {
    }

    class ProductEntityRelationEntity extends Entity
    {
    }
}
