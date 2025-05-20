<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Traits\OrderFixture;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
class OrderRecalculationControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;
    use OrderFixture;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private Context $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = static::getContainer()->get('order.repository');

        $this->context = Context::createDefaultContext();
    }

    public function testPrimaryDeliveryAndTransactionIdStayTheSame(): void
    {
        $orderId = Uuid::randomHex();
        $primaryOrderDeliveryId = Uuid::randomHex();
        $primaryTransactionId = Uuid::randomHex();
        $versionId = Uuid::randomHex();

        $this->createOrder($orderId, $primaryOrderDeliveryId, $primaryTransactionId, $versionId);

        $browser = $this->getBrowser();
        $browser->request('POST', \sprintf('/api/_action/order/%s/recalculate', $orderId));

        $this->context->assign(['versionId' => $versionId]);

        $order = $this->orderRepository->search(new Criteria(), $this->context)->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        static::assertSame(Response::HTTP_NOT_FOUND, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertSame($primaryOrderDeliveryId, $order->getPrimaryOrderDeliveryId());
        static::assertSame($primaryTransactionId, $order->getPrimaryOrderTransactionId());
    }

    private function createOrder(string $orderId, string $primaryOrderDeliveryId, string $primaryTransactionId, string $versionId): void
    {
        $orderData = $this->getOrderData($orderId, $this->context)[0];

        $orderData['versionId'] = $versionId;
        $orderData['orderCustomer']['orderVersionId'] = $versionId;
        $orderData['lineItems'][0]['id'] = Uuid::randomHex();

        $orderData['deliveries'] = [
            $this->getDeliveryData($orderData, $primaryOrderDeliveryId, 20),
            $this->getDeliveryData($orderData, Uuid::randomHex(), 10),
        ];

        $orderData['transactions'] = [
            $this->getTransactionData($orderData, $primaryTransactionId),
            $this->getTransactionData($orderData, Uuid::randomHex()),
        ];

        $this->orderRepository->create([$orderData], $this->context);

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'versionId' => $versionId,
                'primaryOrderTransactionId' => $primaryTransactionId,
                'primaryOrderDeliveryId' => $primaryOrderDeliveryId,
            ],
        ], $this->context);
    }

    /**
     * @param array{lineItems: array<int, array{id: string}>} $orderData
     *
     * @return array<string, mixed>
     */
    private function getDeliveryData(array $orderData, string $id, int $cost): array
    {
        return [
            'id' => $id,
            'stateId' => $this->getStateMachineState(),
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingCosts' => new CalculatedPrice($cost, $cost, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'shippingDateEarliest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
            'shippingDateLatest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
            'shippingOrderAddress' => [
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Floy',
                'lastName' => 'Glover',
                'zipcode' => '59438-0403',
                'city' => 'Stellaberg',
                'street' => 'street',
                'country' => [
                    'name' => 'kasachstan',
                    'id' => $this->getValidCountryId(),
                ],
            ],
            'trackingCodes' => [
                'CODE-1',
                'CODE-2',
            ],
            'positions' => [
                [
                    'price' => new CalculatedPrice($cost, $cost, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'orderLineItemId' => $orderData['lineItems'][0]['id'],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $orderData
     *
     * @return array<string, mixed>
     */
    private function getTransactionData(array $orderData, string $id): array
    {
        return [
            'id' => $id,
            'orderId' => $orderData['id'],
            'orderVersionId' => $orderData['versionId'],
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'amount' => [
                'quantity' => 1,
                'taxRules' => [],
                'listPrice' => null,
                'unitPrice' => 20.02,
                'totalPrice' => 20.02,
                'referencePrice' => null,
                'calculatedTaxes' => [],
                'regulationPrice' => null,
            ],
            'stateId' => $this->getStateMachineState(),
        ];
    }
}
