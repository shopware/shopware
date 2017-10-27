<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Test\Repository;

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderDeliveryPosition\Repository\OrderDeliveryPositionRepository;
use Shopware\OrderDeliveryPosition\Searcher\OrderDeliveryPositionSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderDeliveryPositionRepositoryTest extends KernelTestCase
{
    /**
     * @var OrderDeliveryPositionRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.order_delivery_position.repository');
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
        $this->assertInstanceOf(OrderDeliveryPositionSearchResult::class, $result);
    }
}
