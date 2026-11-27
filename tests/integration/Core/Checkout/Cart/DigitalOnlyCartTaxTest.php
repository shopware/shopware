<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\IntraCommunityTaxResolver;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;
use Shopware\Core\System\Tax\TaxRuleType\IndividualStatesRuleTypeFilter;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
class DigitalOnlyCartTaxTest extends TestCase
{
    use AdminApiTestBehaviour;
    use BasicTestDataBehaviour;
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const FOREIGN_VAT_ID = 'NL123456789B01';
    private const SELLER_VAT_ID = 'DE123456789';
    private const COUNTRIES = ['DE', 'FR', 'NL', 'CH'];
    private const TAX_FREE = ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0];
    private const TAXED = ['enabled' => false, 'currencyId' => Defaults::CURRENCY, 'amount' => 0];

    private IdsCollection $ids;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $container = static::getContainer();
        $context = Context::createDefaultContext();

        foreach (self::COUNTRIES as $iso) {
            $this->ids->set($iso, $this->getCountryIdByIsoCode($iso));
        }

        $container->get('country.repository')->update([
            ['id' => $this->ids->get('DE'), 'customerTax' => self::TAXED, 'companyTax' => self::TAXED],
            ['id' => $this->ids->get('FR'), 'isEu' => true, 'checkVatIdPattern' => true, 'customerTax' => self::TAXED, 'companyTax' => self::TAX_FREE],
            ['id' => $this->ids->get('NL'), 'isEu' => true, 'checkVatIdPattern' => true, 'customerTax' => self::TAXED, 'companyTax' => self::TAX_FREE],
            ['id' => $this->ids->get('CH'), 'isEu' => false, 'customerTax' => self::TAX_FREE, 'companyTax' => self::TAX_FREE],
        ], $context);

        $container->get('tax.repository')->create([[
            'id' => $this->ids->create('tax'),
            'name' => 'Standard',
            'taxRate' => 15,
            'rules' => [
                $this->taxRule('DE', 19),
                $this->taxRule('FR', 20),
                $this->taxRule('NL', 21),
                $this->taxRule('CH', 8.1),
            ],
        ]], $context);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'countryId' => $this->ids->get('DE'),
            'countries' => array_map(fn (string $iso): array => ['id' => $this->ids->get($iso)], self::COUNTRIES),
        ]);

        $container->get('product.repository')->create([
            $this->product('e-book', ProductDefinition::TYPE_DIGITAL),
            $this->product('widget', ProductDefinition::TYPE_PHYSICAL),
        ], $context);

        $container->get('customer_group.repository')->create([[
            'id' => $this->ids->create('net-group'),
            'name' => 'Net customers',
            'displayGross' => false,
            'priceBasis' => CustomerGroupEntity::PRICE_BASIS_NET,
        ]], $context);

        $container->get(SystemConfigService::class)->set('core.basicInformation.sellerCountryId', $this->ids->get('DE'));
        $container->get(VatIdPatternProvider::class)->reset();
        $container->get(IntraCommunityTaxResolver::class)->reset();
    }

    /**
     * @param list<string> $vatIds
     * @param list<array{float, float}> $taxes
     */
    #[DataProvider('digitalOnlyCartProvider')]
    public function testDigitalOnlyCartIsTaxedByTheBillingAddressWithinTheOptIn(array $vatIds, string $billing, string $shipping, string $taxStatus, array $taxes, float $totalPrice): void
    {
        $this->loginCustomer($vatIds, $billing, $shipping);

        $price = $this->addToCart('e-book')['price'];

        static::assertSame($taxStatus, $price['taxStatus']);
        static::assertSame($taxes, $this->getTaxes($price['calculatedTaxes']));
        static::assertSame($totalPrice, (float) $price['totalPrice']);
    }

    /**
     * @return iterable<string, array{list<string>, string, string, string, list<array{float, float}>, float}>
     */
    public static function digitalOnlyCartProvider(): iterable
    {
        yield '1: foreign VAT ID billed outside the EU pays the VAT of the shipping address' => [[self::FOREIGN_VAT_ID], 'CH', 'DE', 'net', [[19.0, 19.0]], 119.0];
        yield '2: foreign VAT ID billed in another member state is exempt' => [[self::FOREIGN_VAT_ID], 'FR', 'DE', 'tax-free', [], 100.0];
        yield '3: foreign VAT ID billed in the seller country pays seller VAT' => [[self::FOREIGN_VAT_ID], 'DE', 'CH', 'net', [[19.0, 19.0]], 119.0];
        yield '4: seller VAT ID billed outside the EU pays the VAT of the shipping address' => [[self::SELLER_VAT_ID], 'CH', 'DE', 'net', [[19.0, 19.0]], 119.0];
        yield '5: seller VAT ID billed in another member state pays seller VAT' => [[self::SELLER_VAT_ID], 'FR', 'CH', 'net', [[19.0, 19.0]], 119.0];
        yield '6: seller VAT ID billed in the seller country pays seller VAT' => [[self::SELLER_VAT_ID], 'DE', 'CH', 'net', [[19.0, 19.0]], 119.0];
        yield '7: no VAT ID billed outside the EU pays the VAT of the shipping address' => [[], 'CH', 'DE', 'net', [[19.0, 19.0]], 119.0];
        yield '8: no VAT ID billed in another member state pays its VAT' => [[], 'FR', 'CH', 'net', [[20.0, 20.0]], 120.0];
        yield '9: no VAT ID billed in the seller country pays seller VAT' => [[], 'DE', 'CH', 'net', [[19.0, 19.0]], 119.0];
        yield '10: malformed VAT ID billed in another member state pays its VAT' => [['DEINVALID'], 'FR', 'CH', 'net', [[20.0, 20.0]], 120.0];
    }

    public function testMemberStateWithoutTaxFreeB2bKeepsTheShippingAddress(): void
    {
        static::getContainer()->get('country.repository')->update([
            ['id' => $this->ids->get('FR'), 'companyTax' => self::TAXED],
        ], Context::createDefaultContext());
        $this->loginCustomer([], 'FR', 'DE');

        $price = $this->addToCart('e-book')['price'];

        static::assertSame([[19.0, 19.0]], $this->getTaxes($price['calculatedTaxes']));
    }

    public function testSellerOutsideTheEuKeepsTheShippingAddress(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set('core.basicInformation.sellerCountryId', $this->ids->get('CH'));
        $this->loginCustomer([], 'FR', 'DE');

        $price = $this->addToCart('e-book')['price'];

        static::assertSame([[19.0, 19.0]], $this->getTaxes($price['calculatedTaxes']));
    }

    public function testBillingCountryStateRuleApplies(): void
    {
        $context = Context::createDefaultContext();
        static::getContainer()->get('country_state.repository')->create([[
            'id' => $this->ids->create('state'),
            'countryId' => $this->ids->get('FR'),
            'shortCode' => 'FR-DIG',
            'name' => 'Digital state',
        ]], $context);
        static::getContainer()->get('tax_rule.repository')->create([
            $this->taxRule('FR', 10, IndividualStatesRuleTypeFilter::TECHNICAL_NAME, ['states' => [$this->ids->get('state')]]) + ['taxId' => $this->ids->get('tax')],
        ], $context);
        $this->loginCustomer([], 'FR', 'CH');
        static::getContainer()->get('customer_address.repository')->update([
            ['id' => $this->ids->get('address-FR'), 'countryStateId' => $this->ids->get('state')],
        ], $context);

        $price = $this->addToCart('e-book')['price'];

        static::assertSame([[10.0, 10.0]], $this->getTaxes($price['calculatedTaxes']));
        static::assertSame(110.0, (float) $price['totalPrice']);
    }

    public function testPrivateGuestKeepsTheShippingAddress(): void
    {
        $this->loginCustomer([], 'FR', 'DE', CustomerEntity::ACCOUNT_TYPE_PRIVATE, TestDefaults::FALLBACK_CUSTOMER_GROUP, guest: true);

        $price = $this->addToCart('e-book')['price'];

        static::assertSame('gross', $price['taxStatus']);
        static::assertSame([19.0], $this->getTaxRates($price['calculatedTaxes']));
    }

    /**
     * @param list<string> $products
     */
    #[DataProvider('cartWithPhysicalProductProvider')]
    public function testCartWithPhysicalProductIsTaxedByTheShippingAddress(array $products, string $billing, string $shipping, string $taxStatus, ?float $taxRate): void
    {
        $this->loginCustomer([self::FOREIGN_VAT_ID], $billing, $shipping);

        $cart = $this->addToCart(...$products);

        static::assertSame($taxStatus, $cart['price']['taxStatus']);
        static::assertCount(\count($products), $cart['lineItems']);
        foreach ($cart['lineItems'] as $lineItem) {
            static::assertSame($taxRate === null ? [] : [$taxRate], $this->getTaxRates($lineItem['price']['calculatedTaxes']));
        }
    }

    /**
     * @return iterable<string, array{list<string>, string, string, string, float|null}>
     */
    public static function cartWithPhysicalProductProvider(): iterable
    {
        yield '14: mixed cart shipped to the seller country pays seller VAT' => [['e-book', 'widget'], 'FR', 'DE', 'net', 19.0];
        yield '15: mixed cart shipped to another member state is exempt' => [['e-book', 'widget'], 'DE', 'FR', 'tax-free', null];
        yield '16: physical-only cart shipped to the seller country pays seller VAT' => [['widget'], 'FR', 'DE', 'net', 19.0];
    }

    public function testCartWithoutCustomerIsTaxedByTheSalesChannelCountry(): void
    {
        $price = $this->addToCart('e-book')['price'];

        static::assertSame('gross', $price['taxStatus']);
        static::assertSame([19.0], $this->getTaxRates($price['calculatedTaxes']));
    }

    public function testProductPricesOutsideTheCartKeepTheShippingAddress(): void
    {
        $this->loginCustomer([], 'FR', 'DE');
        static::assertSame([20.0], $this->getTaxRates($this->addToCart('e-book')['price']['calculatedTaxes']));

        $this->browser->request('POST', '/store-api/product/' . $this->ids->get('e-book'));
        $product = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['product'];

        static::assertSame(19.0, (float) $product['calculatedPrice']['calculatedTaxes'][0]['taxRate']);
    }

    /**
     * @param list<string> $vatIds
     * @param list<float> $taxRates
     */
    #[DataProvider('orderProvider')]
    public function testOrderAndRecalculationKeepTheBillingAddressTaxes(array $vatIds, string $billing, string $shipping, string $taxStatus, array $taxRates, float $amountTotal): void
    {
        $this->loginCustomer($vatIds, $billing, $shipping);
        $this->addToCart('e-book');

        $this->browser->request('POST', '/store-api/checkout/order');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        $orderId = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['id'];

        $this->assertOrder($orderId, Context::createDefaultContext(), $taxStatus, $taxRates, $amountTotal);

        $this->getBrowser()->request('POST', \sprintf('/api/_action/version/order/%s', $orderId));
        $versionId = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['versionId'];
        $this->getBrowser()->request('POST', \sprintf('/api/_action/order/%s/recalculate', $orderId), [], [], [
            'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
        ]);
        static::assertSame(204, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

        $this->assertOrder($orderId, Context::createDefaultContext()->createWithVersionId($versionId), $taxStatus, $taxRates, $amountTotal);
    }

    /**
     * @return iterable<string, array{list<string>, string, string, string, list<float>, float}>
     */
    public static function orderProvider(): iterable
    {
        yield '21, 24: tax-free order stays tax-free with a taxed default shipping address' => [[self::FOREIGN_VAT_ID], 'FR', 'DE', 'tax-free', [], 100.0];
        yield '22, 23: order taxed by the billing country keeps its VAT with an exempt default shipping address' => [[], 'FR', 'CH', 'net', [20.0], 120.0];
    }

    /**
     * @param list<string> $vatIds
     */
    private function loginCustomer(
        array $vatIds,
        string $billing,
        string $shipping,
        string $accountType = CustomerEntity::ACCOUNT_TYPE_BUSINESS,
        ?string $groupId = null,
        bool $guest = false,
    ): void {
        $customerId = $this->ids->create('customer');
        static::getContainer()->get('customer.repository')->create([[
            'id' => $customerId,
            'salesChannelId' => $this->ids->get('sales-channel'),
            'groupId' => $groupId ?? $this->ids->get('net-group'),
            'accountType' => $accountType,
            'guest' => $guest,
            'company' => 'Digital Goods Ltd',
            'vatIds' => $vatIds,
            'email' => $customerId . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => $customerId,
            'defaultBillingAddress' => $this->address($billing),
            'defaultShippingAddress' => $this->address($shipping),
        ]], Context::createDefaultContext());

        $token = $this->ids->create('token');
        static::getContainer()->get(SalesChannelContextPersister::class)->save(
            $token,
            [SalesChannelContextService::CUSTOMER_ID => $customerId],
            $this->ids->get('sales-channel'),
            $customerId,
        );
        $this->browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }

    /**
     * @return array<string, mixed> the cart as `GET /store-api/checkout/cart` returns it
     */
    private function addToCart(string ...$products): array
    {
        $this->browser->jsonRequest('POST', '/store-api/checkout/cart/line-item', [
            'items' => array_map(fn (string $product): array => [
                'id' => $this->ids->get($product),
                'referencedId' => $this->ids->get($product),
                'type' => 'product',
                'quantity' => 1,
            ], $products),
        ]);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        $added = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->browser->request('GET', '/store-api/checkout/cart');
        $cart = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // adding calculates new line items before they know their product type, loading calculates stored ones
        static::assertSame($added['price'], $cart['price']);

        return $cart;
    }

    /**
     * @param list<array<string, mixed>> $calculatedTaxes
     *
     * @return list<array{float, float}> rate and amount of each calculated tax
     */
    private function getTaxes(array $calculatedTaxes): array
    {
        return array_map(static fn (array $tax): array => [(float) $tax['taxRate'], (float) $tax['tax']], $calculatedTaxes);
    }

    /**
     * @param list<array<string, mixed>> $calculatedTaxes
     *
     * @return list<float>
     */
    private function getTaxRates(array $calculatedTaxes): array
    {
        return array_map(static fn (array $tax): float => (float) $tax['taxRate'], $calculatedTaxes);
    }

    /**
     * @param list<float> $taxRates
     */
    private function assertOrder(string $orderId, Context $context, string $taxStatus, array $taxRates, float $amountTotal): void
    {
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        $order = $orderRepository->search((new Criteria([$orderId]))->addAssociation('lineItems'), $context)->getEntities()->first();
        static::assertNotNull($order);

        static::assertSame($taxStatus, $order->getPrice()->getTaxStatus());
        static::assertSame($amountTotal, $order->getAmountTotal());
        $lineItem = $order->getLineItems()?->first();
        static::assertNotNull($lineItem);
        static::assertSame($taxRates, array_values($lineItem->getPrice()?->getCalculatedTaxes()->map(static fn ($tax): float => $tax->getTaxRate()) ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    private function product(string $key, string $type): array
    {
        return [
            'id' => $this->ids->create($key),
            'productNumber' => $key,
            'name' => $key,
            'type' => $type,
            'stock' => 10,
            'taxId' => $this->ids->get('tax'),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 119, 'net' => 100, 'linked' => false]],
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function address(string $iso): array
    {
        return [
            'id' => $this->ids->create('address-' . $iso),
            'countryId' => $this->ids->get($iso),
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Main Street 1',
            'city' => 'Capital',
            'zipcode' => '12345',
        ];
    }

    /**
     * @param array<string, mixed>|null $data
     *
     * @return array<string, mixed>
     */
    private function taxRule(string $iso, float $taxRate, string $type = EntireCountryRuleTypeFilter::TECHNICAL_NAME, ?array $data = null): array
    {
        $typeId = static::getContainer()->get('tax_rule_type.repository')->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', $type)),
            Context::createDefaultContext()
        )->firstId();

        return ['countryId' => $this->ids->get($iso), 'taxRate' => $taxRate, 'taxRuleTypeId' => $typeId, 'data' => $data];
    }
}
