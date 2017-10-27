<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Test\Repository;

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\PriceGroupDiscount\Repository\PriceGroupDiscountRepository;
use Shopware\PriceGroupDiscount\Searcher\PriceGroupDiscountSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PriceGroupDiscountRepositoryTest extends KernelTestCase
{
    /**
     * @var PriceGroupDiscountRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.price_group_discount.repository');
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
        $this->assertInstanceOf(PriceGroupDiscountSearchResult::class, $result);
    }
}
