<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Repository;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class CustomerGroupValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerGroupRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerGroupRepository = $this->getContainer()->get('customer_group.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
    }

    public function testDeletionOfDefaultGroup(): void
    {
        $customerGroupId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->customerGroupRepository->create([[
            'id' => $customerGroupId,
            'name' => 'Exchange for default',
            'registrationTitle' => 'Exchange for default',
            'registrationIntroduction' => 'Exchange for default',
            'registrationOnlyCompanyRegistration' => false,
            'registrationSeoMetaDescription' => '',
        ]], $context);

        // make default customer group unused elsewhere
        $this->salesChannelRepository->update(array_map(static function (string $id) use ($customerGroupId): array {
            return [
                'id' => $id,
                'customerGroupId' => $customerGroupId,
            ];
        }, $this->salesChannelRepository->searchIds(new Criteria(), $context)->getIds()), $context);
        $this->customerRepository->update(array_map(static function (string $id) use ($customerGroupId): array {
            return [
                'id' => $id,
                'customerGroupId' => $customerGroupId,
            ];
        }, $this->customerRepository->searchIds(new Criteria(), $context)->getIds()), $context);

        $this->expectException(WriteException::class);
        $this->expectExceptionMessageMatches('/The default customer group ' . Defaults::FALLBACK_CUSTOMER_GROUP . ' cannot be deleted/');
        $this->customerGroupRepository->delete([['id' => Defaults::FALLBACK_CUSTOMER_GROUP]], $context);
    }
}
