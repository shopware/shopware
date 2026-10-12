<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductPrice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductPriceDefinition::class)]
class ProductPriceDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(ProductPriceDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(ProductPriceEntity::class, $definition->getEntityClass());
        static::assertSame(ProductPriceCollection::class, $definition->getCollectionClass());
    }

    public function testQuantityRangeFieldsRequireAMinimumOfOne(): void
    {
        $fields = $this->createDefinition()->getFields();

        $start = $fields->get('quantityStart');
        static::assertInstanceOf(IntField::class, $start);
        static::assertTrue($start->is(Required::class));
        static::assertSame(1, $start->getMinValue());

        $end = $fields->get('quantityEnd');
        static::assertInstanceOf(IntField::class, $end);
        static::assertSame(1, $end->getMinValue());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testQuantityRangeFieldsCarryNoMinimumInTheLegacyMajor(): void
    {
        $fields = $this->createDefinition()->getFields();

        $start = $fields->get('quantityStart');
        static::assertInstanceOf(IntField::class, $start);
        static::assertNull($start->getMinValue());
    }

    private function createDefinition(): ProductPriceDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ProductPriceDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(ProductPriceDefinition::ENTITY_NAME);
        static::assertInstanceOf(ProductPriceDefinition::class, $definition);

        return $definition;
    }
}
