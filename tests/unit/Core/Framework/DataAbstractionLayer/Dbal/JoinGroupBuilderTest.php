<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinGroup;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder
 */
#[Package('core')]
class JoinGroupBuilderTest extends TestCase
{
    public function testCanGroupProvidedFilters(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
                ProductCategoryDefinition::class,
                CategoryDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $definition = $registry->get(ProductDefinition::class);

        $filters = [
            new EqualsFilter('active', true),
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('stock', 10),
                new EqualsFilter('categories.type', 'test'),
            ]),
            new MultiFilter(MultiFilter::CONNECTION_OR),
        ];

        $builder = new JoinGroupBuilder();
        $groupedFilters = $builder->group($filters, $definition, ['product.categories']);

        static::assertCount(3, $groupedFilters);
        static::assertInstanceOf(EqualsFilter::class, $groupedFilters[0]);
        static::assertInstanceOf(EqualsFilter::class, $groupedFilters[1]);
        static::assertInstanceOf(JoinGroup::class, $groupedFilters[2]);
    }
}
