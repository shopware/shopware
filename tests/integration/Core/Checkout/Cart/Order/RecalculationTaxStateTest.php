<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class RecalculationTaxStateTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private string $germanyId;

    private string $austriaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->germanyId = $this->getCountryIdByIso('DE');
        $this->austriaId = $this->getCountryIdByIso('AT');

        // Companies are tax-free in Austria
        static::getContainer()->get('country.repository')->update([[
            'id' => $this->austriaId,
            'companyTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0.0],
            'checkVatIdPattern' => false,
        ]], $this->context);
    }

    public function testGermanOrderStaysTaxableAfterTheMatchingCustomerAddressWasEdited(): void
    {
        $austrianAddressId = Uuid::randomHex();
        $germanAddressId = Uuid::randomHex();
        $customerId = $this->createBusinessCustomer(
            defaultAddress: $this->getAddressData($austrianAddressId, $this->austriaId, 'Getreidegasse 9', '5020', 'Salzburg'),
            otherAddress: $this->getAddressData($germanAddressId, $this->germanyId, 'Ebbinghoff 10', '48624', 'Schöppingen'),
        );
        $orderId = $this->createOrder(
            $customerId,
            $this->getAddressData(Uuid::randomHex(), $this->germanyId, 'Ebbinghoff 10', '48624', 'Schöppingen'),
        );

        // The customer's German address no longer matches the order address
        static::getContainer()->get('customer_address.repository')->update([[
            'id' => $germanAddressId,
            'street' => 'Ebbinghoff 11',
        ]], $this->context);

        $versionContext = $this->context->createWithVersionId($this->orderRepository->createVersion($orderId, $this->context));
        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $order = $this->orderRepository->search(new Criteria([$orderId]), $versionContext)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);
        static::assertNotSame(CartPrice::TAX_STATE_FREE, $order->getTaxStatus());
        static::assertGreaterThan(0.0, $order->getPrice()->getCalculatedTaxes()->getAmount());
    }

    /**
     * @param array<string, string> $defaultAddress
     * @param array<string, string> $otherAddress
     */
    private function createBusinessCustomer(array $defaultAddress, array $otherAddress): string
    {
        $customerId = Uuid::randomHex();

        static::getContainer()->get('customer.repository')->create([[
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'defaultBillingAddressId' => $defaultAddress['id'],
            'defaultShippingAddressId' => $defaultAddress['id'],
            'customerNumber' => 'CUSTOMER-1',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => $customerId . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'shopware AG',
            'vatIds' => ['ATU12345678'],
            'addresses' => [
                ['customerId' => $customerId, ...$defaultAddress],
                ['customerId' => $customerId, ...$otherAddress],
            ],
        ]], $this->context);

        return $customerId;
    }

    /**
     * @param array<string, string> $address
     */
    private function createOrder(string $customerId, array $address): string
    {
        $orderId = Uuid::randomHex();
        $deliveryId = Uuid::randomHex();
        $lineItemId = Uuid::randomHex();
        $stateIdLoader = static::getContainer()->get(InitialStateIdLoader::class);
        $rounding = ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true];
        $taxRules = new TaxRuleCollection([new TaxRule(19)]);

        $this->orderRepository->create([[
            'id' => $orderId,
            'orderNumber' => Uuid::randomHex(),
            'price' => new CartPrice(100, 119, 100, new CalculatedTaxCollection(), $taxRules, CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'stateId' => $stateIdLoader->get(OrderStates::STATE_MACHINE),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'itemRounding' => $rounding,
            'totalRounding' => $rounding,
            'billingAddressId' => $address['id'],
            'addresses' => [$address],
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
            ],
            'lineItems' => [[
                'id' => $lineItemId,
                'identifier' => $lineItemId,
                'quantity' => 1,
                'type' => 'custom',
                'label' => 'Test',
                'price' => new CalculatedPrice(100, 100, new CalculatedTaxCollection(), $taxRules),
                'priceDefinition' => new QuantityPriceDefinition(100, $taxRules),
            ]],
            'deliveries' => [[
                'id' => $deliveryId,
                'stateId' => $stateIdLoader->get(OrderDeliveryStates::STATE_MACHINE),
                'shippingMethodId' => $this->getValidShippingMethodId(),
                'shippingOrderAddressId' => $address['id'],
                'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'shippingDateEarliest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
                'shippingDateLatest' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
            ]],
        ]], $this->context);

        $this->orderRepository->update([['id' => $orderId, 'primaryOrderDeliveryId' => $deliveryId]], $this->context);

        return $orderId;
    }

    /**
     * @return array<string, string>
     */
    private function getAddressData(string $id, string $countryId, string $street, string $zipcode, string $city): array
    {
        return [
            'id' => $id,
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'company' => 'shopware AG',
            'street' => $street,
            'zipcode' => $zipcode,
            'city' => $city,
            'countryId' => $countryId,
        ];
    }

    private function getCountryIdByIso(string $iso): string
    {
        $countryId = static::getContainer()->get('country.repository')
            ->searchIds((new Criteria())->addFilter(new EqualsFilter('iso', $iso)), $this->context)
            ->firstId();
        static::assertIsString($countryId);

        return $countryId;
    }
}
