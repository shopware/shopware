<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document\Zugferd;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdBuilder;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdDocument;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ZugferdBuilder::class)]
#[CoversClass(ZugferdDocument::class)]
class ZugferdBuilderTest extends TestCase
{
    private int $position = 0;

    private float $totalAmount = 0.0;

    protected function tearDown(): void
    {
        $this->position = 0;
    }

    public function testUnsupportedTax(): void
    {
        $this->expectException(DocumentException::class);
        $this->expectExceptionMessage('Unsupported tax status');

        $order = new OrderEntity();
        $order->setTaxStatus('random-tax');

        (new ZugferdBuilder($this->createMock(EventDispatcherInterface::class)))
            ->buildDocument($order, new DocumentGenerateOperation('order-id'), new DocumentConfiguration(), Context::createDefaultContext());
    }

    public function testBuildDocument(): void
    {
        $order = $this->buildOrder();

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setIso('UK');

        $config = [
            'documentNumber' => 'test-1000',
            'companyCountryId' => $country->getId(),
            'companyCountry' => $country,
            'companyStreet' => 'Musterstreet 1',
            'companyZipcode' => '12345',
            'companyCity' => 'Mustercity',
            'companyName' => 'Muster company SE',
            'companyEmail' => 'test@example.de',
            'companyPhone' => '0123456789',
            'executiveDirector' => 'Max Mustermann',
            'placeOfJurisdiction' => 'Muster',
            'taxNumber' => '0123456789',
            'vatId' => '012356789',
            'paymentDueDate' => '+30 day'
        ];

        $xmlContent = (new ZugferdBuilder($this->createMock(EventDispatcherInterface::class)))
            ->buildDocument($order, new DocumentGenerateOperation('order-id'), DocumentConfigurationFactory::createConfiguration($config), Context::createDefaultContext());;

        $totalAmount = number_format($this->totalAmount,2, '.', '');

        static::assertStringStartsWith('<?xml', $xmlContent);
        static::assertStringContainsString("LineTotalAmount>$totalAmount<", $xmlContent);
        static::assertStringContainsString('ChargeTotalAmount>20.00<', $xmlContent);
        static::assertStringContainsString('AllowanceTotalAmount>20.00<', $xmlContent);
        static::assertStringContainsString('TaxBasisTotalAmount>1020.00<', $xmlContent);
        static::assertStringContainsString('TaxTotalAmount currencyID="EUR">193.80<', $xmlContent);
        static::assertStringContainsString('GrandTotalAmount>1213.80<', $xmlContent);
        static::assertStringContainsString('DuePayableAmount>1213.80<', $xmlContent);

        foreach ($config as $key => $value) {
            match(true) {
                str_starts_with($key, 'companyCountry') => static::assertStringContainsString('UK', $xmlContent),
                $key === 'paymentDueDate' => static::assertStringContainsString('DueDateDateTime', $xmlContent),
                default => static::assertStringContainsString($value, $xmlContent),
            };
        }

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);

        foreach ($lineItems as $lineItem) {
            $this->assertLineItemProperties($lineItem, $xmlContent);
        }

        $customerData = array_filter($order->getOrderCustomer()?->getVars() ?? []);
        static::assertNotEmpty($customerData);

        foreach ($customerData as $value) {
            static::assertStringContainsString($value, $xmlContent);
        }
    }

    private function assertLineItemProperties(OrderLineItemEntity $lineItem, string $xmlContent): void
    {
        if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
            $quantity = number_format($lineItem->getQuantity(), 2, '.', '');
            $unitPrice = number_format($lineItem->getUnitPrice() / 1.19, 2, '.', '');
            $totalPrice = number_format($lineItem->getTotalPrice() / 1.19, 2, '.', '');

            static::assertStringContainsString("LineID>{$this->getPosition($lineItem)}<", $xmlContent);
            static::assertStringContainsString("Name>{$lineItem->getLabel()}<", $xmlContent);
            static::assertStringContainsString("ChargeAmount>$unitPrice<", $xmlContent);
            static::assertStringContainsString("BasisQuantity unitCode=\"H87\">$quantity<", $xmlContent);
            static::assertStringContainsString("BilledQuantity unitCode=\"H87\">$quantity<", $xmlContent);
            static::assertStringContainsString("LineTotalAmount>$totalPrice<", $xmlContent);
            static::assertStringContainsString("Name>{$lineItem->getLabel()}<", $xmlContent);
        }

        if ($lineItem->getChildren()) {
            foreach ($lineItem->getChildren() as $child) {
                $this->assertLineItemProperties($child, $xmlContent);
            }
        }
    }

    private function getPosition(OrderLineItemEntity $lineItem): string {
        $position = '';

        if ($lineItem->getParent()) {
            $position .= $this->getPosition($lineItem->getParent()) . '-';
        }

        return $position . $lineItem->getPosition();
    }

    protected function buildOrder(): OrderEntity {
        $normalId = Uuid::randomHex();
        $bundleId = Uuid::randomHex();
        $bundleFirstId = Uuid::randomHex();
        $bundleSecondId = Uuid::randomHex();
        $promotion1Id = Uuid::randomHex();

        $orderLineItemCollection = new OrderLineItemCollection(
            [
                $normal = $this->buildOrderLineItemEntity($normalId, LineItem::PRODUCT_LINE_ITEM_TYPE),
                $bundle = $this->buildOrderLineItemEntity($bundleId, LineItem::CONTAINER_LINE_ITEM),
                $promotion1 = $this->buildOrderLineItemEntity($promotion1Id, LineItem::PROMOTION_LINE_ITEM_TYPE),
                $this->buildOrderLineItemEntity(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE),
            ]
        );

        $bundleFirst = $this->buildOrderLineItemEntity($bundleFirstId, LineItem::PRODUCT_LINE_ITEM_TYPE, $bundle, 2);
        $bundleSecond = $this->buildOrderLineItemEntity($bundleSecondId, LineItem::PRODUCT_LINE_ITEM_TYPE, $bundle);

        $this->setPrice($normal, $bundleFirst, $bundleSecond);
        $promotion1->setUnitPrice(-20 * 1.19);
        $promotion1->setTotalPrice($promotion1->getUnitPrice());

        $promotion1->setPrice(new CalculatedPrice(
            $promotion1->getUnitPrice(),
            $promotion1->getTotalPrice(),
            new CalculatedTaxCollection([
                new CalculatedTax($promotion1->getUnitPrice() + 20, 19, $promotion1->getTotalPrice()),
            ]),
            new TaxRuleCollection()
        ));

        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $address = $this->getOrderAddress();
        $order = new OrderEntity();
        $order->setTaxStatus('gross');
        $order->setLineItems($orderLineItemCollection);
        $order->setOrderCustomer($this->getOrderCustomer());
        $order->setBillingAddressId($address->getId());
        $order->setAddresses(new OrderAddressCollection([$address]));
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setAmountTotal(1213.8);
        $order->setAmountNet(1020);
        $order->setCurrency($currency);
        $order->setPrice(new CartPrice(
            1000,
            1190,
            1190,
            new CalculatedTaxCollection([new CalculatedTax(119, 19, 1190)]),
            new TaxRuleCollection(),
            'gross'
        ));

        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());
        $delivery->setShippingDateLatest(new \DateTimeImmutable());
        $delivery->setShippingCosts(new CalculatedPrice(
            23.8,
            23.8,
            new CalculatedTaxCollection([
                new CalculatedTax(3.8, 19, 23.8),
            ]),
            new TaxRuleCollection()
        ));

        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        return $order;
    }

    /**
     * @param string[] $states
     */
    private function buildOrderLineItemEntity(string $id, string $type, ?OrderLineItemEntity $parent = null, int $quantity = 1, array $states = []): OrderLineItemEntity
    {
        $orderLineItemEntity = new OrderLineItemEntity();
        $orderLineItemEntity->setId($id);
        $orderLineItemEntity->setType($type);
        $orderLineItemEntity->setPosition(++$this->position);
        $orderLineItemEntity->setIdentifier($id);
        $orderLineItemEntity->setLabel(Uuid::randomHex());
        $orderLineItemEntity->setGood(true);
        $orderLineItemEntity->setRemovable(true);
        $orderLineItemEntity->setStackable(false);
        $orderLineItemEntity->setQuantity($quantity);
        $orderLineItemEntity->setStates($states);
        $orderLineItemEntity->setChildren(new OrderLineItemCollection());
        $orderLineItemEntity->setParent($parent);

        $parent?->getChildren()?->add($orderLineItemEntity);

        return $orderLineItemEntity;
    }

    private function getOrderCustomer(): OrderCustomerEntity
    {
        $customer = new OrderCustomerEntity();
        $customer->setEmail('order-customer-email');
        $customer->setFirstName('order-customer-first-name');
        $customer->setLastName('order-customer-last-name');
        $customer->setCustomerNumber('order-customer-number');
        $customer->setCompany('order-customer-company');

        return $customer;
    }

    private function getOrderAddress(): OrderAddressEntity
    {
        $country = new CountryEntity();
        $country->setId('country-id');
        $country->setName('country-name');
        $country->setIso('DE');

        $countryState = new CountryStateEntity();
        $countryState->setId('country-state-id');
        $countryState->setName('country-state-name');
        $countryState->setShortCode('DE-TEST');

        $address = new OrderAddressEntity();
        $address->setId('order-address-id');
        $address->setVersionId('order-address-version-id');
        $address->setSalutationId('order-address-salutation-id');
        $address->setFirstName('order-address-first-name');
        $address->setLastName('order-address-last-name');
        $address->setStreet('order-address-street');
        $address->setZipcode('order-address-zipcode');
        $address->setCity('order-address-city');
        $address->setCountryId('order-address-country-id');
        $address->setCountryStateId('order-address-country-state-id');
        $address->setCountry($country);
        $address->setCountryState($countryState);

        return $address;
    }

    private function setPrice(OrderLineItemEntity ...$items): void
    {
        foreach ($items as $item) {
            $item->setUnitPrice($item->getPosition() * 119);
            $item->setTotalPrice($item->getUnitPrice() * $item->getQuantity());

            $this->totalAmount += $item->getTotalPrice() / 1.19;

            $item->setPrice(new CalculatedPrice(
                $item->getUnitPrice(),
                $item->getTotalPrice(),
                new CalculatedTaxCollection([
                    new CalculatedTax($item->getTotalPrice()-$item->getTotalPrice() / 1.19, 19, $item->getTotalPrice()),
                ]),
                new TaxRuleCollection(),
                $item->getQuantity()
            ));
        }
    }
}
