<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document;

use horstoeko\zugferd\ZugferdDocumentReader;
use horstoeko\zugferd\ZugferdXsdValidator;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\PromotionLineItemFactory;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\IntraCommunityTaxResolver;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader as DocumentV2ConfigLoader;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\DocumentV2\DocumentV2Trait;

/**
 * A German shop selling to a French business customer, the fixture of the e-invoice VAT category story.
 * Order A, the intra-community delivery, is {@see self::placeOrder()} with its defaults.
 *
 * @internal
 */
#[Package('after-sales')]
trait IntraCommunitySupplyTrait
{
    use CountryAddToSalesChannelTestBehaviour;
    use DocumentV2Trait;

    private const VAT_ID = 'FR12345678901';

    private const SELLER_VAT_ID = 'DE123456789';

    private const SELLER_TAX_NUMBER = '12/345/67890';

    private const FR_WITHOUT_ZIP_CODE = 'FR without zip code';

    private const RAM_NAMESPACE = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private const UDT_NAMESPACE = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    private const BREAKDOWN = '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax';

    private string $customerId;

    /**
     * @var array<string, string> ISO code => id of the customer's address in that country
     */
    private array $addressIds;

    private string $shippingMethodId;

    private IdsCollection $ids;

    /**
     * The orders of the story whose e-invoice states no intra-community supply, with the category their XML keeps.
     *
     * @return iterable<string, array{0: array{billingCountry?: string, shippingCountry?: string, vatIds?: list<string>, accountType?: string, product?: string, withPromotion?: bool}, 1: string, 2?: string}>
     */
    public static function ordersWithoutIntraCommunitySupply(): iterable
    {
        yield 'order B, taxed with the rate of the seller country' => [['vatIds' => [self::SELLER_VAT_ID], 'withPromotion' => false], 'S'];
        yield 'order C, tax-free delivery to a third country' => [['billingCountry' => 'CH', 'shippingCountry' => 'CH', 'withPromotion' => false], 'Z'];
        yield 'order D, tax-free private customer' => [['billingCountry' => 'CH', 'shippingCountry' => 'CH', 'vatIds' => [], 'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE, 'withPromotion' => false], 'Z'];
        yield 'order H, tax-free without delivery' => [['product' => 'ebook', 'withPromotion' => false], 'Z'];
        // The default French pattern requires the country code since 6.6, the one shipped before did not
        yield 'order E, tax-free with a VAT ID without country code' => [['vatIds' => ['12345678901']], 'Z', '(FR)?[0-9A-Z]{2}[0-9]{9}'];
        yield 'order G, tax-free to a delivery address without zip code' => [['billingCountry' => 'DE', 'shippingCountry' => self::FR_WITHOUT_ZIP_CODE], 'Z'];
    }

    private function setUpIntraCommunitySupply(): void
    {
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        // Without a seller country the shop supplies from the sales channel's default country, Germany
        static::getContainer()->get(SystemConfigService::class)->set('core.basicInformation.sellerCountryId', null);

        $companyTax = ['enabled' => true, 'amount' => 0, 'currencyId' => Defaults::CURRENCY];
        static::getContainer()->get('country.repository')->update([
            ['id' => $this->getCountryId('FR'), 'active' => true, 'isEu' => true, 'checkVatIdPattern' => true, 'vatIdRequired' => false, 'companyTax' => $companyTax],
            ['id' => $this->getCountryId('CH'), 'active' => true, 'isEu' => false, 'companyTax' => $companyTax, 'customerTax' => $companyTax],
        ], $this->context);
        $this->addCountriesToSalesChannel([$this->getCountryId('FR'), $this->getCountryId('CH')]);
        $this->resetIntraCommunityDecision();

        static::getContainer()->get('customer_group.repository')->update([
            ['id' => TestDefaults::FALLBACK_CUSTOMER_GROUP, 'displayGross' => false],
        ], $this->context);

        $this->createProducts();
        $this->shippingMethodId = $this->createShippingMethod(10.0);
        $this->createTestFixturePercentagePromotion(Uuid::randomHex(), 'TENOFF', 10.0, null, static::getContainer());
        $this->createBusinessCustomer();

        foreach (['invoice', 'storno', 'credit_note'] as $documentType) {
            $this->upsertBaseConfig([
                ...$this->getDemoInvoiceLegacyConfig(),
                'companyCountryId' => $this->getCountryId('DE'),
                'vatId' => self::SELLER_VAT_ID,
                'taxNumber' => self::SELLER_TAX_NUMBER,
                'paymentDueDate' => '+30 days',
                'displayAdditionalNoteDelivery' => false,
            ], $documentType);
        }
    }

    /**
     * @param list<string> $vatIds
     */
    private function placeOrder(
        string $billingCountry = 'FR',
        string $shippingCountry = 'FR',
        array $vatIds = [self::VAT_ID],
        string $accountType = CustomerEntity::ACCOUNT_TYPE_BUSINESS,
        string $product = 'widget',
        bool $withPromotion = true,
    ): string {
        static::getContainer()->get('customer.repository')->update([[
            'id' => $this->customerId,
            'accountType' => $accountType,
            'vatIds' => $vatIds,
            'defaultBillingAddressId' => $this->addressIds[$billingCountry],
            'defaultShippingAddressId' => $this->addressIds[$shippingCountry],
        ]], $this->context);

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->customerId,
                SalesChannelContextService::SHIPPING_METHOD_ID => $this->shippingMethodId,
                SalesChannelContextService::PAYMENT_METHOD_ID => $this->getInvoicePaymentMethodId(),
            ]
        );

        $cartService = static::getContainer()->get(CartService::class);
        $cart = $cartService->createNew($this->salesChannelContext->getToken());
        $productId = $this->ids->get($product);
        $lineItems = [(new ProductLineItemFactory(new PriceDefinitionFactory()))->create(['id' => $productId, 'referencedId' => $productId], $this->salesChannelContext)];
        if ($withPromotion) {
            $lineItems[] = (new PromotionLineItemFactory())->create(['referencedId' => 'TENOFF'], $this->salesChannelContext);
        }

        $orderId = $this->persistCart($cartService->add($cart, $lineItems, $this->salesChannelContext));
        $this->enrichOrderForRendering($orderId);

        return $orderId;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function changeDocumentConfig(string $documentType, array $config, ?string $salesChannelId = null): void
    {
        $this->upsertBaseConfig($config + (array) $this->getBaseConfig($documentType)?->getConfig(), $documentType, $salesChannelId);

        static::getContainer()->get(DocumentConfigLoader::class)->reset();
        static::getContainer()->get(DocumentV2ConfigLoader::class)->reset();
    }

    private function changeFrenchVatIdCheck(bool $checkVatIdPattern, ?string $vatIdPattern = null): void
    {
        static::getContainer()->get('country.repository')->update([
            array_filter([
                'id' => $this->getCountryId('FR'),
                'checkVatIdPattern' => $checkVatIdPattern,
                'vatIdPattern' => $vatIdPattern,
            ], static fn ($value) => $value !== null),
        ], $this->context);

        $this->resetIntraCommunityDecision();
    }

    private function resetIntraCommunityDecision(): void
    {
        static::getContainer()->get(VatIdPatternProvider::class)->reset();
        static::getContainer()->get(IntraCommunityTaxResolver::class)->reset();
    }

    private function getInvoicePaymentMethodId(): string
    {
        $paymentMethodId = static::getContainer()->get('payment_method.repository')->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'payment_invoicepayment')),
            $this->context
        )->firstId();
        static::assertIsString($paymentMethodId);

        return $paymentMethodId;
    }

    private function getCountryId(string $iso): string
    {
        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');

        $countryId = $countryRepository->searchIds((new Criteria())->addFilter(new EqualsFilter('iso', $iso)), $this->context)->firstId();
        static::assertIsString($countryId);

        return $countryId;
    }

    private function getShippingDateLatest(string $orderId): string
    {
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');

        $order = $orderRepository->search((new Criteria([$orderId]))->addAssociation('deliveries'), $this->context)->getEntities()->first();
        $shippingDateLatest = $order?->getDeliveries()?->first()?->getShippingDateLatest();
        static::assertNotNull($shippingDateLatest);

        return $shippingDateLatest->format('Ymd');
    }

    /**
     * Criteria 1, 2, 3 and 4 of the story, for any e-invoice of order A.
     */
    private function assertIntraCommunitySupply(string $xml, string $orderId, string $taxableAmount): void
    {
        $this->assertCategory($xml, 'K', '0.00');
        $this->assertExemptBreakdown($xml, $taxableAmount);
        $this->assertVatIds($xml);
        $this->assertDeliverTo($xml, $orderId);
    }

    /**
     * Every line, allowance, charge and breakdown has the category at the rate.
     */
    private function assertCategory(string $xml, string $category, string $rate): void
    {
        $categories = self::values($xml, '//ram:CategoryCode');
        static::assertNotSame([], $categories);
        static::assertSame(array_fill(0, \count($categories), $category), $categories);

        $rates = self::values($xml, '//ram:RateApplicablePercent');
        static::assertSame(array_fill(0, \count($rates), $rate), $rates);
    }

    private function assertExemptBreakdown(string $xml, string $taxableAmount): void
    {
        static::assertSame(['K'], self::values($xml, self::BREAKDOWN . '/ram:CategoryCode'));
        static::assertSame(['0.00'], self::values($xml, self::BREAKDOWN . '/ram:RateApplicablePercent'));
        static::assertSame(['0.00'], self::values($xml, self::BREAKDOWN . '/ram:CalculatedAmount'));
        static::assertSame([$taxableAmount], self::values($xml, self::BREAKDOWN . '/ram:BasisAmount'));
        static::assertSame(['VATEX-EU-IC'], self::values($xml, self::BREAKDOWN . '/ram:ExemptionReasonCode'));
        static::assertSame(['Intra-community supply'], self::values($xml, self::BREAKDOWN . '/ram:ExemptionReason'));
    }

    private function assertVatIds(string $xml, string $sellerVatId = self::SELLER_VAT_ID): void
    {
        static::assertSame([self::VAT_ID], self::values($xml, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]'));
        static::assertSame([$sellerVatId], self::values($xml, '//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]'));
        static::assertSame([self::SELLER_TAX_NUMBER], self::values($xml, '//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="FC"]'));
    }

    private function assertDeliverTo(string $xml, string $orderId): void
    {
        $address = '//ram:ShipToTradeParty/ram:PostalTradeAddress/';
        static::assertSame(['1 Rue de Rivoli'], self::values($xml, $address . 'ram:LineOne'));
        static::assertSame(['75001'], self::values($xml, $address . 'ram:PostcodeCode'));
        static::assertSame(['Paris'], self::values($xml, $address . 'ram:CityName'));
        static::assertSame(['FR'], self::values($xml, $address . 'ram:CountryID'));
        static::assertSame([$this->getShippingDateLatest($orderId)], self::values($xml, '//ram:ActualDeliverySupplyChainEvent//udt:DateTimeString'));
    }

    /**
     * The XML stays as it is without this story: no exemption reason, no buyer VAT ID and no deliver-to address.
     */
    private function assertNoIntraCommunitySupply(string $xml, string $category): void
    {
        $this->assertCategory($xml, $category, $category === 'S' ? '19.00' : '0.00');
        static::assertSame([], self::values($xml, '//ram:ExemptionReason'));
        static::assertSame([], self::values($xml, '//ram:ExemptionReasonCode'));
        static::assertSame([], self::values($xml, '//ram:BuyerTradeParty/ram:SpecifiedTaxRegistration'));
        static::assertSame([], self::values($xml, '//ram:ShipToTradeParty'));
    }

    private function assertXsdValid(string $xml): void
    {
        $validator = (new ZugferdXsdValidator(ZugferdDocumentReader::readAndGuessFromContent($xml)))->validate();

        static::assertSame([], $validator->validationErrors());
    }

    /**
     * @return list<string>
     */
    private static function values(string $xml, string $query): array
    {
        static::assertNotSame('', $xml);
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('ram', self::RAM_NAMESPACE);
        $xpath->registerNamespace('udt', self::UDT_NAMESPACE);

        $nodes = $xpath->query($query);
        static::assertInstanceOf(\DOMNodeList::class, $nodes);

        $values = [];
        foreach ($nodes as $node) {
            $values[] = trim((string) $node->nodeValue);
        }

        return $values;
    }

    private function createProducts(): void
    {
        static::getContainer()->get('product.repository')->create([
            (new ProductBuilder($this->ids, 'widget'))
                ->name('Widget')
                ->price(119.0, 100.0)
                ->tax('standard', 19)
                ->type(ProductDefinition::TYPE_PHYSICAL)
                ->visibility()
                ->build(),
            (new ProductBuilder($this->ids, 'ebook'))
                ->name('E-book')
                ->price(119.0, 100.0)
                ->tax('standard', 19)
                ->type(ProductDefinition::TYPE_DIGITAL)
                ->visibility()
                ->build(),
        ], $this->context);

        $entireCountry = static::getContainer()->get('tax_rule_type.repository')->searchIds(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', EntireCountryRuleTypeFilter::TECHNICAL_NAME)),
            $this->context
        )->firstId();

        static::getContainer()->get('tax_rule.repository')->create([
            ['taxId' => $this->ids->get('standard'), 'taxRuleTypeId' => $entireCountry, 'countryId' => $this->getCountryId('DE'), 'taxRate' => 19.0],
            ['taxId' => $this->ids->get('standard'), 'taxRuleTypeId' => $entireCountry, 'countryId' => $this->getCountryId('FR'), 'taxRate' => 20.0],
        ], $this->context);
    }

    private function createBusinessCustomer(): void
    {
        $this->customerId = Uuid::randomHex();
        $this->addressIds = [
            'DE' => Uuid::randomHex(),
            'FR' => Uuid::randomHex(),
            'CH' => Uuid::randomHex(),
            self::FR_WITHOUT_ZIP_CODE => Uuid::randomHex(),
        ];

        $addresses = [];
        foreach ([
            'DE' => ['DE', 'Unter den Linden 1', '10115', 'Berlin'],
            'FR' => ['FR', '1 Rue de Rivoli', '75001', 'Paris'],
            'CH' => ['CH', 'Bahnhofstrasse 1', '8001', 'Zürich'],
            self::FR_WITHOUT_ZIP_CODE => ['FR', '1 Rue de Rivoli', '', 'Paris'],
        ] as $key => [$iso, $street, $zipcode, $city]) {
            $addresses[] = [
                'id' => $this->addressIds[$key],
                'countryId' => $this->getCountryId($iso),
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => $street,
                'zipcode' => $zipcode,
                'city' => $city,
            ];
        }

        static::getContainer()->get('customer.repository')->create([[
            'id' => $this->customerId,
            'customerNumber' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'company' => 'Exemple SARL',
            'email' => 'test@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'guest' => true,
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'vatIds' => [self::VAT_ID],
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $this->addressIds['FR'],
            'defaultShippingAddressId' => $this->addressIds['FR'],
            'addresses' => $addresses,
        ]], $this->context);
    }
}
