<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Aggregate\CustomerGroup;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class CustomerGroupDefaultsTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testACreateWithoutTheTaxDisplayAndPriceBasisGetsTheDefaultPairing(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $customerGroup = $this->createGroupWithoutPriceFields();

        static::assertTrue($customerGroup->getDisplayGross());
        static::assertSame(CustomerGroupEntity::PRICE_BASIS_GROSS, $customerGroup->getPriceBasis());
    }

    public function testACreateWithoutThePriceBasisKeepsItNullBeforeTheMajor(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $customerGroup = $this->createGroupWithoutPriceFields();

        static::assertTrue($customerGroup->getDisplayGross());
        static::assertNull($customerGroup->getPriceBasis());
    }

    private function createGroupWithoutPriceFields(): CustomerGroupEntity
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        /** @var EntityRepository<CustomerGroupCollection> $repository */
        $repository = static::getContainer()->get('customer_group.repository');
        $repository->create([['id' => $id, 'name' => 'field unaware group']], $context);

        $customerGroup = $repository->search(new Criteria([$id]), $context)->getEntities()->first();
        static::assertNotNull($customerGroup);

        return $customerGroup;
    }
}
