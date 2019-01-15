<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Tax\TaxDefinition;

class CachedEntitySearcherTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCacheHit()
    {
        $dbalSearcher = $this->createMock(EntitySearcher::class);

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $criteria = new Criteria();
        $context = Context::createDefaultContext();
        $dbalSearcher->expects(static::exactly(2))
            ->method('search')
            ->will(
                $this->returnValue(
                    new IdSearchResult(
                        0,
                        [
                            $id1 => ['id' => $id1],
                            $id2 => ['id' => $id2],
                        ],
                        $criteria,
                        $context
                    )
                )
            );

        $cache = $this->getContainer()->get('shopware.cache');
        $cache->clear();

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedSearcher = new CachedEntitySearcher($generator, $cache, $dbalSearcher);

        $context = Context::createDefaultContext();

        //first call should not match and the expects of the dbal searcher should called
        $databaseResult = $cachedSearcher->search(TaxDefinition::class, $criteria, $context);

        //second call should hit the cache items and the dbal searcher shouldn't be called
        $cachedResult = $cachedSearcher->search(TaxDefinition::class, $criteria, $context);

        static::assertEquals($databaseResult, $cachedResult);

        $criteria->addSorting(new FieldSorting('tax.name'));

        //after changing the criteria, the cache shouldn't hit
        $cachedSearcher->search(TaxDefinition::class, $criteria, $context);
    }
}
