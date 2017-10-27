<?php declare(strict_types=1);

namespace Shopware\ProductListingPrice\Test\Repository;

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductListingPrice\Repository\ProductListingPriceRepository;
use Shopware\ProductListingPrice\Searcher\ProductListingPriceSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductListingPriceRepositoryTest extends KernelTestCase
{
    /**
     * @var ProductListingPriceRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.product_listing_price_ro.repository');
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
        $this->assertInstanceOf(ProductListingPriceSearchResult::class, $result);
    }
}
