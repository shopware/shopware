<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Version;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class VersioningCustomFieldTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $orderRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testCustomFieldOrderVersioning(): void
    {
        $id = Uuid::randomHex();
        $versionId = $this->context->getVersionId();

        $order = $this->getOrderFixture($id, $versionId);

        // create order + order version and belonging context
        $this->orderRepository->create([$order], $this->context);
        $versionedOrderId = $this->orderRepository->createVersion($id, $this->context);
        $versionedContext = $this->context->createWithVersionId($versionedOrderId);

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search(new Criteria([$id]), $this->context)->first();

        /** @var OrderEntity $versionedOrder */
        $versionedOrder = $this->orderRepository->search(new Criteria([$id]), $versionedContext)->first();

        // custom fields should be correctly copied from original order to versioned order
        static::assertEquals($order->getCustomFields(), $versionedOrder->getCustomFields());
    }

    public function testCustomFieldMergeBackVersioning(): void
    {
        $id = Uuid::randomHex();
        $versionId = $this->context->getVersionId();

        $order = $this->getOrderFixture($id, $versionId);

        // create order + order version and belonging context
        $this->orderRepository->create([$order], $this->context);
        $versionedOrderId = $this->orderRepository->createVersion($id, $this->context);
        $versionedContext = $this->context->createWithVersionId($versionedOrderId);

        // update versioned order's custom fields
        $this->orderRepository->update([[
            'id' => $id,
            'customFields' => [
                'custom_test' => 1,
                'custom_test_new' => 'this is a test',
            ],
        ]], $versionedContext);

        // merge back version into original order
        $this->orderRepository->merge($versionedOrderId, $this->context);

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search(new Criteria([$id]), $this->context)->first();

        // custom field update should be applied from versioned order to original order
        static::assertEquals(
            [
                'custom_test' => 1,
                'custom_test_new' => 'this is a test',
            ],
            $order->getCustomFields()
        );
    }

    private function getOrderFixture(string $orderId, string $orderVersionId): array
    {
        return [
            'id' => $orderId,
            'versionId' => $orderVersionId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'customerId' => Uuid::randomHex(),
            'billingAddressId' => Uuid::randomHex(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.00,
            'price' => [
                'netPrice' => 1000.00,
                'totalPrice' => 1000.00,
                'positionPrice' => 1000.00,
                'calculatedTaxes' => [
                    [
                        'tax' => 0.0,
                        'taxRate' => 0.0,
                        'price' => 0.00,
                        'extensions' => [],
                    ],
                ],
                'taxRules' => [
                    [
                        'taxRate' => 0.0,
                        'extensions' => [],
                        'percentage' => 100.0,
                    ],
                ],
                'taxStatus' => 'gross',
                'rawTotal' => 1000.00,
            ],
            'shippingCosts' => [
                'unitPrice' => 0.0,
                'totalPrice' => 0.0,
                'listPrice' => null,
                'referencePrice' => null,
                'quantity' => 1,
                'calculatedTaxes' => [
                    [
                        'tax' => 0.0,
                        'taxRate' => 0.0,
                        'price' => 0.0,
                        'extensions' => [],
                    ],
                ],
                'taxRules' => [
                    [
                        'taxRate' => 0.0,
                        'extensions' => [],
                        'percentage' => 100,
                    ],
                ],
            ],
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'stateId' => Uuid::randomHex(),
            'orderDateTime' => new \DateTime(),
            'customFields' => [
                'custom_test' => 0,
            ],
        ];
    }
}
