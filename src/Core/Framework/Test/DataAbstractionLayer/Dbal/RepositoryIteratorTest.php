<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\SystemConfig\SystemConfigCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
class RepositoryIteratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testIteratedSearch(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepository<SystemConfigCollection> $systemConfigRepository */
        $systemConfigRepository = $this->getContainer()->get('system_config.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('configurationKey', 'core'));
        $criteria->setLimit(1);

        /** @var RepositoryIterator<SystemConfigCollection> $iterator */
        $iterator = new RepositoryIterator($systemConfigRepository, $context, $criteria);

        $offset = 1;
        while (($result = $iterator->fetch()) !== null) {
            static::assertNotEmpty($result->getEntities()->first()?->getId());
            static::assertEquals(
                [new ContainsFilter('configurationKey', 'core')],
                $criteria->getFilters()
            );
            static::assertCount(0, $criteria->getPostFilters());
            static::assertEquals($offset, $criteria->getOffset());
            ++$offset;
        }
    }

    public function testFetchIdsIsNotRunningInfinitely(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepository<SystemConfigCollection> $systemConfigRepository */
        $systemConfigRepository = $this->getContainer()->get('system_config.repository');

        $iterator = new RepositoryIterator($systemConfigRepository, $context, new Criteria());

        $iteration = 0;
        while ($iterator->fetchIds() !== null && $iteration < 100) {
            ++$iteration;
        }

        static::assertTrue($iteration < 100);
    }

    public function testFetchIdAutoIncrement(): void
    {
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');

        $context = Context::createDefaultContext();

        $productRepository->create([
            (new ProductBuilder(new IdsCollection(), 'product1'))->price(1)->build(),
            (new ProductBuilder(new IdsCollection(), 'product2'))->price(1)->build(),
            (new ProductBuilder(new IdsCollection(), 'product3'))->price(1)->build(),
        ], $context);

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $iterator = new RepositoryIterator($productRepository, $context, $criteria);

        $totalFetchedIds = 0;
        while ($iterator->fetchIds()) {
            ++$totalFetchedIds;
        }
        static::assertEquals($totalFetchedIds, 3);
    }

    public function testFetchNotObviousEmptyNextRequestAutoIncrement(): void
    {
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $context = Context::createDefaultContext();

        $productRepository->create([
            (new ProductBuilder(new IdsCollection(), 'product1'))->price(1)->build(),
            (new ProductBuilder(new IdsCollection(), 'product2'))->price(1)->build(),
            (new ProductBuilder(new IdsCollection(), 'product3'))->price(1)->build(),
        ], $context);

        $criteria = new Criteria();
        $criteria->setTitle('test__product_iteration');
        // 3 products will be fetched in pairs of 2
        // The last response will obviously have 1 (less than 2) item and this already indicates end
        // so this should not need an additional search
        $criteria->setLimit(2);

        $searchesCount = 0;
        $eventDispatcher->addListener(EntitySearchedEvent::class, function (EntitySearchedEvent $event) use (&$searchesCount): void {
            if ($event->getCriteria()->getTitle() === 'test__product_iteration') {
                ++$searchesCount;
            }
        });

        $iterator = new RepositoryIterator($productRepository, $context, $criteria);

        while ($iterator->fetchIds() !== null) {
            // fetch all ids and count searches
        }

        static::assertSame(2, $searchesCount, '2 searches are enough to fetch 3 products by limit 2');

        $searchesCount = 0;
        $iterator->reset(); // removes increment filter

        while ($iterator->fetch() !== null) {
            // fetch all entities and count searches
        }

        static::assertSame(2, $searchesCount, '2 searches are enough to fetch 3 products by limit 2');
    }

    public function testFetchNotObviousEmptyNextRequestLimitOffset(): void
    {
        /** @var EntityRepository<CountryStateCollection> $countryStateRepository */
        $countryStateRepository = $this->getContainer()->get('country_state.repository');
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->setTitle('test__country_state_iteration');
        // 16 German country states will be fetched in pairs of 5
        // The last response will obviously have 1 (less than 5) item and this already indicates end
        // so this should not need an additional search
        $criteria->setLimit(5);
        $criteria->addFilter(new EqualsFilter('country.iso', 'DE'));

        $searchesCount = 0;
        $eventDispatcher->addListener(EntitySearchedEvent::class, function (EntitySearchedEvent $event) use (&$searchesCount): void {
            if ($event->getCriteria()->getTitle() === 'test__country_state_iteration') {
                ++$searchesCount;
            }
        });

        $iterator = new RepositoryIterator($countryStateRepository, $context, $criteria);

        $countFetchedIds = 0;

        while (($fetchedIds = $iterator->fetchIds()) !== null) {
            // fetch all ids and count searches
            $countFetchedIds += \count($fetchedIds);
        }

        static::assertSame(4, $searchesCount, '4 searches are enough to fetch 16 German country states by limit 5');
        static::assertSame(16, $countFetchedIds);

        $searchesCount = 0;
        $countFetchedEntities = 0;
        $iterator->reset(); // removes offset

        while (($fetchedEntities = $iterator->fetch()) !== null) {
            // fetch all entities and count searches
            $countFetchedEntities += $fetchedEntities->count();
        }

        static::assertSame(4, $searchesCount, '4 searches are enough to fetch 16 German country states by limit 5');
        static::assertSame(16, $countFetchedEntities);
    }

    public function testAutomaticIdIterationCanBeStartedWithoutManualReset(): void
    {
        /** @var EntityRepository<ProductCollection> $countryStateRepository */
        $countryStateRepository = $this->getContainer()->get('country_state.repository');

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->setLimit(5); // 16 are expected therefore multiple requests are needed
        $criteria->addFilter(new EqualsFilter('country.iso', 'DE'));

        $iterator = new RepositoryIterator($countryStateRepository, $context, $criteria);
        $idsRun1 = [...$iterator->iterateIds()];
        $idsRun2 = [...$iterator->iterateIds()];

        static::assertNotEmpty($idsRun1);
        static::assertCount(16, $idsRun1, 'We expected 16 DE states but got less, this could be a array-key issue');
        static::assertEqualsCanonicalizing($idsRun1, $idsRun2);
    }

    public function testAutomaticEntityIterationCanBeStartedWithoutManualReset(): void
    {
        /** @var EntityRepository<ProductCollection> $countryStateRepository */
        $countryStateRepository = $this->getContainer()->get('country_state.repository');

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->setLimit(5); // 16 are expected therefore multiple requests are needed
        $criteria->addFilter(new EqualsFilter('country.iso', 'DE'));

        $iterator = new RepositoryIterator($countryStateRepository, $context, $criteria);
        $entitiesRun1 = [...$iterator->iterateEntities()];
        $entitiesRun2 = [...$iterator->iterateEntities()];

        static::assertNotEmpty($entitiesRun1);
        static::assertCount(16, $entitiesRun1, 'We expected 16 DE states but got less, this could be a array-key issue');

        foreach ($entitiesRun1 as $state) {
            static::assertInstanceOf(CountryStateEntity::class, $state);
            static::assertArrayHasKey($state->getUniqueIdentifier(), $entitiesRun2);
        }
    }

    public function testAutomaticEntityIterationCanBeStartedAgainEvenAfterAnException(): void
    {
        /** @var EntityRepository<ProductCollection> $countryStateRepository */
        $countryStateRepository = $this->getContainer()->get('country_state.repository');
        /** @var EventDispatcher $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->setTitle('test__country_state_exception_iteration');
        $criteria->setLimit(5); // 16 are expected therefore multiple requests are needed
        $criteria->addFilter(new EqualsFilter('country.iso', 'DE'));

        $searchesCount = 0;
        $iterator = new RepositoryIterator($countryStateRepository, $context, $criteria);

        $eventDispatcher->addListener(EntitySearchedEvent::class, function (EntitySearchedEvent $event) use (&$searchesCount): void {
            if ($event->getCriteria()->getTitle() === 'test__country_state_exception_iteration') {
                ++$searchesCount;
            }

            if ($searchesCount == 2) {
                throw new \RuntimeException('This is expected', 1716055400);
            }
        });

        try {
            foreach ($iterator->iterateEntities() as $_) {
                // fetch all entities and count searches
            }

            static::fail('This should have been failing earlier');
        } catch (\RuntimeException $exception) {
            static::assertSame(1716055400, $exception->getCode(), 'This is not the exception we expected');
        }

        // this should now run again normally
        $entitiesRun1 = [...$iterator->iterateEntities()];
        static::assertNotEmpty($entitiesRun1);
        static::assertCount(16, $entitiesRun1, 'We expected 16 DE states but got less, this could be a array-key issue');
    }
}
