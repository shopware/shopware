<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Test\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\ShippingMethodPrice\Repository\ShippingMethodPriceRepository;
use Shopware\ShippingMethodPrice\Searcher\ShippingMethodPriceSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ShippingMethodPriceRepositoryTest extends KernelTestCase
{
    /**
     * @var ShippingMethodPriceRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.shipping_method_price.repository');
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
        $this->assertInstanceOf(ShippingMethodPriceSearchResult::class, $result);
    }
}
