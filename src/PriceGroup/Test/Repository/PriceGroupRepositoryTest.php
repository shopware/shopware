<?php declare(strict_types=1);

namespace Shopware\PriceGroup\Test\Repository;

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\PriceGroup\Repository\PriceGroupRepository;
use Shopware\PriceGroup\Searcher\PriceGroupSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PriceGroupRepositoryTest extends KernelTestCase
{
    /**
     * @var PriceGroupRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.price_group.repository');
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
        $this->assertInstanceOf(PriceGroupSearchResult::class, $result);
    }
}
