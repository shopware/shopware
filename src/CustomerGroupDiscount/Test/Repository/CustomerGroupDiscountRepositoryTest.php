<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Test\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroupDiscount\Repository\CustomerGroupDiscountRepository;
use Shopware\CustomerGroupDiscount\Searcher\CustomerGroupDiscountSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CustomerGroupDiscountRepositoryTest extends KernelTestCase
{
    /**
     * @var CustomerGroupDiscountRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.customer_group_discount.repository');
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
        $this->assertInstanceOf(CustomerGroupDiscountSearchResult::class, $result);
    }
}
