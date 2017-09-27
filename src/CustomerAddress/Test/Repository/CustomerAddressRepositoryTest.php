<?php

namespace Shopware\CustomerAddress\Test\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerAddress\Repository\CustomerAddressRepository;
use Shopware\CustomerAddress\Searcher\CustomerAddressSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CustomerAddressRepositoryTest extends KernelTestCase
{
    /**
     * @var CustomerAddressRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.customer_address.repository');
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
        $this->assertInstanceOf(CustomerAddressSearchResult::class, $result);
    }
}
