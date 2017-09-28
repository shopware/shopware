<?php declare(strict_types=1);

namespace Shopware\Shop\Test\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\Shop\Repository\ShopRepository;
use Shopware\Shop\Searcher\ShopSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ShopRepositoryTest extends KernelTestCase
{
    /**
     * @var ShopRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.shop.repository');
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
        $this->assertInstanceOf(ShopSearchResult::class, $result);
    }
}
