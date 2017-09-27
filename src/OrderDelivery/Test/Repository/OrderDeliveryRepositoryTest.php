<?php

namespace Shopware\OrderDelivery\Test\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderDelivery\Repository\OrderDeliveryRepository;
use Shopware\OrderDelivery\Searcher\OrderDeliverySearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderDeliveryRepositoryTest extends KernelTestCase
{
    /**
     * @var OrderDeliveryRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.order_delivery.repository');
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
        $this->assertInstanceOf(OrderDeliverySearchResult::class, $result);
    }
}
