<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDefinition::class)]
class ProductDefinitionTest extends TestCase
{
    public function testSearchFields(): void
    {
        // don't change this list, each additional field will reduce the performance

        $registry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class)
        );

        $definition = $registry->getByEntityName('product');

        $fields = $definition->getFields();

        $searchable = $fields->filterByFlag(SearchRanking::class);

        $keys = $searchable->getKeys();

        // NEVER add an association to this list!!! otherwise, the API query takes too long and shops with many products (more than 1000) will fail
        $expected = ['customSearchKeywords', 'productNumber', 'manufacturerNumber', 'ean', 'name'];

        sort($expected);
        sort($keys);

        static::assertSame($expected, $keys);
    }

    public function testDefaultsMakeANewProductAPhysicalSellableItem(): void
    {
        $defaults = $this->getDefinition()->getDefaults();

        static::assertSame(ProductDefinition::TYPE_PHYSICAL, $defaults['type']);
        static::assertFalse($defaults['isCloseout']);
        static::assertTrue($defaults['active']);
        static::assertSame(1, $defaults['minPurchase']);
    }

    public function testChildDefaultsOnlyPresetTheType(): void
    {
        static::assertSame(['type' => ProductDefinition::TYPE_PHYSICAL], $this->getDefinition()->getChildDefaults());
    }

    public function testSince(): void
    {
        static::assertSame('6.0.0.0', $this->getDefinition()->since());
    }

    private function getDefinition(): ProductDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class)
        );

        $definition = $registry->getByEntityName('product');
        static::assertInstanceOf(ProductDefinition::class, $definition);

        return $definition;
    }
}
