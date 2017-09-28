<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Test\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Shopware\SeoUrl\Repository\SeoUrlRepository;
use Shopware\SeoUrl\Searcher\SeoUrlSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SeoUrlRepositoryTest extends KernelTestCase
{
    /**
     * @var SeoUrlRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.seo_url.repository');
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
        $this->assertInstanceOf(SeoUrlSearchResult::class, $result);
    }
}
