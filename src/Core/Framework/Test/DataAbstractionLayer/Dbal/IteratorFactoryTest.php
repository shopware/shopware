<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Query\Expression\CompositeExpression;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class IteratorFactoryTest extends TestCase
{
    use KernelTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    public function testOnlyActiveEntitiesAreSelected(): void
    {
        /** @var IteratorFactory $iteratorFactory */
        $iteratorFactory = $this->getContainer()->get(IteratorFactory::class);
        $offsetQuery = $iteratorFactory->createIterator($this->getContainer()->get(ProductDefinition::class));

        /** @var CompositeExpression $queryParts */
        $queryParts = $offsetQuery->getQuery()
            ->getQueryPart('where');

        static::assertEquals(2, $queryParts->count());
        static::assertEquals(CompositeExpression::TYPE_AND, $queryParts->getType());
        static::assertEquals('(`product`.active = 1) AND (`product`.auto_increment > :lastId)', (string) $queryParts);
    }

    public function testEntitiesWithoutActiveFlag(): void
    {
        /** @var IteratorFactory $iteratorFactory */
        $iteratorFactory = $this->getContainer()->get(IteratorFactory::class);
        $offsetQuery = $iteratorFactory->createIterator($this->getContainer()->get(CmsPageDefinition::class));

        static::assertNull($offsetQuery->getQuery()->getQueryPart('where'));
    }
}
