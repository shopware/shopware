<?php declare(strict_types=1);

namespace Shopware\Order\Test\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Order\Repository\OrderRepository;
use Shopware\Order\Searcher\OrderSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderRepositoryTest extends KernelTestCase
{
    /**
     * @var OrderRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.order.repository');
    }

    public function testSearchUuidsReturnsUuidSearchResult()
    {
        $context = new TranslationContext('SWAG-SHOP-UUID-1', true, null);
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $result = $this->repository->searchUuids($criteria, $context);

        $this->assertInstanceOf(UuidSearchResult::class, $result);
    }

    public function testSearchReturnsSearchResult()
    {
        $context = new TranslationContext('SWAG-SHOP-UUID-1', true, null);
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $result = $this->repository->search($criteria, $context);
        $this->assertInstanceOf(OrderSearchResult::class, $result);
    }
}
