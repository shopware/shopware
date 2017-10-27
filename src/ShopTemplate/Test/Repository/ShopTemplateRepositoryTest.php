<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Test\Repository;

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\UuidSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\ShopTemplate\Repository\ShopTemplateRepository;
use Shopware\ShopTemplate\Searcher\ShopTemplateSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ShopTemplateRepositoryTest extends KernelTestCase
{
    /**
     * @var ShopTemplateRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.shop_template.repository');
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
        $this->assertInstanceOf(ShopTemplateSearchResult::class, $result);
    }
}
