<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Test\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderLineItem\Repository\OrderLineItemRepository;
use Shopware\OrderLineItem\Searcher\OrderLineItemSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OrderLineItemRepositoryTest extends KernelTestCase
{
    /**
     * @var OrderLineItemRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.order_line_item.repository');
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
        $this->assertInstanceOf(OrderLineItemSearchResult::class, $result);
    }
}
