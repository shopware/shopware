<?php declare(strict_types=1);

namespace Shopware\ProductDetailPrice\Test\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\ProductDetailPrice\Repository\ProductDetailPriceRepository;
use Shopware\ProductDetailPrice\Searcher\ProductDetailPriceSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductDetailPriceRepositoryTest extends KernelTestCase
{
    /**
     * @var ProductDetailPriceRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.product_detail_price.repository');
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
        $this->assertInstanceOf(ProductDetailPriceSearchResult::class, $result);
    }
}
