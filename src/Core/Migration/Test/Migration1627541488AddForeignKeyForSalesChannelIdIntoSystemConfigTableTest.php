<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('core')]
class Migration1627541488AddForeignKeyForSalesChannelIdIntoSystemConfigTableTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $salesChannelRepository;

    private EntityRepository $systemConfigRepository;

    protected function setUp(): void
    {
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $this->systemConfigRepository = $this->getContainer()->get('system_config.repository');
    }

    public function testSalesChannelSystemConfigShouldBeDeletedWhenDeletingASalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $data = [
            'id' => $salesChannelId,
            'name' => 'test',
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getValidCountryId(),
            'accessKey' => $salesChannelId,
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
        ];

        $this->salesChannelRepository->create([$data], Context::createDefaultContext());

        $configData = [
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => 'core.cart.showCustomerComment',
                'configurationValue' => json_encode(['_value' => true]),
                'salesChannelId' => $salesChannelId,
            ],
            [
                'id' => Uuid::randomHex(),
                'configurationKey' => 'core.address.showZipcodeInFrontOfCity',
                'configurationValue' => json_encode(['_value' => true]),
                'salesChannelId' => $salesChannelId,
            ],
        ];

        // add some config for new sales channel
        $this->systemConfigRepository->create($configData, Context::createDefaultContext());

        // delete sales channel
        $this->salesChannelRepository->delete([['id' => $salesChannelId]], Context::createDefaultContext());

        $salesChannel = $this->salesChannelRepository->search(new Criteria([$salesChannelId]), Context::createDefaultContext())->get($salesChannelId);

        static::assertNull($salesChannel);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $systemConfig = $this->systemConfigRepository->search($criteria, Context::createDefaultContext())->getElements();

        static::assertEmpty($systemConfig);
    }
}
