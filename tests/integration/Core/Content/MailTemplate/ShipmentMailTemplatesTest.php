<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MailTemplate;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Integration\Traits\OrderFixture;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('after-sales')]
class ShipmentMailTemplatesTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderFixture;

    private const FIXTURE_FOLDER = __DIR__ . '/../../../../../src/Core/Migration/Fixtures/mails/';

    private const SECOND_STREET = 'Second Street 2';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function templateProvider(): iterable
    {
        $types = [
            'order_confirmation_mail',
            'order_transaction.state.paid',
            'order_transaction.state.reminded',
            'order_delivery.state.cancelled',
            'order_delivery.state.returned',
            'order_delivery.state.returned_partially',
            'order_delivery.state.shipped',
            'order_delivery.state.shipped_partially',
        ];

        foreach ($types as $type) {
            foreach (['en-html', 'en-plain', 'de-html', 'de-plain'] as $variant) {
                yield $type . ' ' . $variant => [$type, $variant];
            }
        }
    }

    #[DataProvider('templateProvider')]
    public function testRendersAnOrderWithOneShipmentTheSameOnThePreviousVersion(string $type, string $variant): void
    {
        $order = $this->loadOrder($this->createOrder(false));

        static::assertSame(
            $this->render($type, $variant, ['order' => $order]),
            $this->render($type, $variant, ['order' => self::asOnThePreviousVersion($order)])
        );
    }

    #[DataProvider('templateProvider')]
    public function testRendersAnOrderWithSeveralShipmentsOnThePreviousVersion(string $type, string $variant): void
    {
        $order = $this->loadOrder($this->createOrder(true));
        $data = ['order' => self::asOnThePreviousVersion($order)];

        if (str_starts_with($type, 'order_delivery.')) {
            $data['orderDelivery'] = $order->getDeliveries()?->firstWhere(
                static fn ($delivery) => $delivery->getShippingOrderAddress()?->getStreet() === self::SECOND_STREET
            );
        }

        static::assertStringContainsString(self::SECOND_STREET, $this->render($type, $variant, $data));
    }

    /**
     * The order as workers of the previous version load it: its deliveries have no getShipments()
     */
    private static function asOnThePreviousVersion(OrderEntity $order): object
    {
        /** @implements \IteratorAggregate<string, OrderDeliveryEntity> */
        $deliveries = new class($order->getDeliveries() ?? new OrderDeliveryCollection()) implements \IteratorAggregate {
            public function __construct(private readonly OrderDeliveryCollection $deliveries)
            {
            }

            public function getIterator(): \Traversable
            {
                return $this->deliveries->getIterator();
            }

            public function first(): ?OrderDeliveryEntity
            {
                return $this->deliveries->first();
            }
        };

        return new class($order, $deliveries) {
            public function __construct(
                private readonly OrderEntity $order,
                private readonly object $deliveries,
            ) {
            }

            public function __isset(string $name): bool
            {
                return method_exists($this->order, 'get' . ucfirst($name));
            }

            public function __get(string $name): mixed
            {
                if ($name === 'deliveries') {
                    return $this->deliveries;
                }

                // @phpstan-ignore method.dynamicName
                return $this->order->{'get' . ucfirst($name)}();
            }
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $type, string $variant, array $data): string
    {
        $context = Context::createDefaultContext();

        $criteria = new Criteria([TestDefaults::SALES_CHANNEL]);
        $criteria->addAssociation('domains');
        $salesChannel = static::getContainer()->get('sales_channel.repository')->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        $template = (string) file_get_contents(self::FIXTURE_FOLDER . $type . '/' . $variant . '.html.twig');
        $renderer = static::getContainer()->get(StringTemplateRenderer::class);
        $renderer->disableTestMode();

        return $renderer->render(
            $template,
            [...$data, 'salesChannel' => $salesChannel, 'a11yDocuments' => []],
            $context,
            str_ends_with($variant, 'html')
        );
    }

    private function createOrder(bool $withSecondShipment): string
    {
        $context = Context::createDefaultContext();
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);

        $salesChannel = static::getContainer()->get('sales_channel.repository')->search(new Criteria([TestDefaults::SALES_CHANNEL]), $context)->getEntities()->first();
        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);
        $orderData[0]['transactions'] = [[
            'paymentMethodId' => $salesChannel->getPaymentMethodId(),
            'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE),
            'amount' => new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]];

        if ($withSecondShipment) {
            $firstDelivery = $orderData[0]['deliveries'][0];

            foreach ([[5.0, self::SECOND_STREET], [-2.0, $firstDelivery['shippingOrderAddress']['street']]] as [$shippingCosts, $street]) {
                $orderData[0]['deliveries'][] = [
                    'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderDeliveryStates::STATE_MACHINE),
                    'shippingMethodId' => $firstDelivery['shippingMethodId'],
                    'shippingCosts' => new CalculatedPrice($shippingCosts, $shippingCosts, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'shippingDateEarliest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
                    'shippingDateLatest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
                    'shippingOrderAddress' => [
                        'salutationId' => $firstDelivery['shippingOrderAddress']['salutationId'],
                        'firstName' => 'Floy',
                        'lastName' => 'Glover',
                        'zipcode' => '59438-0403',
                        'city' => 'Stellaberg',
                        'street' => $street,
                        'countryId' => $firstDelivery['shippingOrderAddress']['country']['id'],
                    ],
                ];
            }
        }

        static::getContainer()->get('order.repository')->create($orderData, $context);

        return $orderId;
    }

    private function loadOrder(string $orderId): OrderEntity
    {
        $order = static::getContainer()->get(OrderProvider::class)->getData($orderId, Context::createDefaultContext());
        static::assertInstanceOf(OrderEntity::class, $order);

        return $order;
    }
}
