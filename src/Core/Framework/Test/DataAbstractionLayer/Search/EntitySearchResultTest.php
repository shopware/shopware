<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;

class EntitySearchResultTest extends TestCase
{
    /**
     * @dataProvider resultPageCriteriaDataProvider
     */
    public function testResultPage(Criteria $criteria, int $page): void
    {
        $entity = new ArrayEntity(['id' => Uuid::randomHex()]);
        $entityCollection = new EntityCollection([$entity]);
        $result = new EntitySearchResult(
            ArrayEntity::class,
            100,
            $entityCollection,
            null,
            $criteria,
            Context::createDefaultContext()
        );

        static::assertSame($page, $result->getPage());
    }

    public function resultPageCriteriaDataProvider(): \Generator
    {
        yield [(new Criteria())->setLimit(5)->setOffset(0), 1];
        yield [(new Criteria())->setLimit(5)->setOffset(1), 1];
        yield [(new Criteria())->setLimit(5)->setOffset(9), 2];
        yield [(new Criteria())->setLimit(5)->setOffset(10), 3];
        yield [(new Criteria())->setLimit(5)->setOffset(11), 3];
        yield [(new Criteria())->setLimit(10)->setOffset(25), 3];
    }
}
