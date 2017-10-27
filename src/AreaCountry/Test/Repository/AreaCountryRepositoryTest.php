<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Test\Repository;

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\AreaCountry\Repository\AreaCountryRepository;
use Shopware\AreaCountry\Searcher\AreaCountrySearchResult;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AreaCountryRepositoryTest extends KernelTestCase
{
    /**
     * @var AreaCountryRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.area_country.repository');
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
        $this->assertInstanceOf(AreaCountrySearchResult::class, $result);
    }
}
