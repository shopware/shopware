<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Test\Repository;

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\AreaCountryState\Repository\AreaCountryStateRepository;
use Shopware\AreaCountryState\Searcher\AreaCountryStateSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AreaCountryStateRepositoryTest extends KernelTestCase
{
    /**
     * @var AreaCountryStateRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.area_country_state.repository');
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
        $this->assertInstanceOf(AreaCountryStateSearchResult::class, $result);
    }
}
