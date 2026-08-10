<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
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

    // @deprecated tag:v6.8.0 - Remove, `product.availableStock` no longer exists
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testAvailableStockIsDeprecatedAndReplacedByStock(): void
    {
        $field = $this->getDefinition()->getField('availableStock');

        static::assertNotNull($field);

        $deprecated = $field->getFlag(Deprecated::class);
        static::assertInstanceOf(Deprecated::class, $deprecated);
        static::assertSame('stock', $deprecated->getReplaceBy());
        static::assertTrue($deprecated->isRemovedInVersion(680));
    }

    public function testAvailableStockIsRemovedWithTheNextMajor(): void
    {
        static::assertNull($this->getDefinition()->getField('availableStock'));
        static::assertNotNull($this->getDefinition()->getField('stock'));
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
