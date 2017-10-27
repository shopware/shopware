<?php declare(strict_types=1);

namespace Shopware\Area\Test\Repository;

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Area\Repository\AreaRepository;
use Shopware\Area\Searcher\AreaSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AreaRepositoryTest extends KernelTestCase
{
    /**
     * @var AreaRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.area.repository');
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
        $this->assertInstanceOf(AreaSearchResult::class, $result);
    }
}
