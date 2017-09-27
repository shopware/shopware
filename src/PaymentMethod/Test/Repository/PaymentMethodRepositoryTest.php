<?php

namespace Shopware\PaymentMethod\Test\Repository;

use Shopware\Context\Struct\TranslationContext;
use Shopware\PaymentMethod\Repository\PaymentMethodRepository;
use Shopware\PaymentMethod\Searcher\PaymentMethodSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\UuidSearchResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PaymentMethodRepositoryTest extends KernelTestCase
{
    /**
     * @var PaymentMethodRepository
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$kernel->getContainer()->get('shopware.payment_method.repository');
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
        $this->assertInstanceOf(PaymentMethodSearchResult::class, $result);
    }
}
