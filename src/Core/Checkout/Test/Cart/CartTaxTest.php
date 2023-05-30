<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
class CartTaxTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepository
     */
    private $productRepository;

    /**
     * @var EntityRepository
     */
    private $customerRepository;

    /**
     * @var EntityRepository
     */
    private $countryRepository;

    /**
     * @var EntityRepository
     */
    private $currencyRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->countryRepository = $this->getContainer()->get('country.repository');
        $this->currencyRepository = $this->getContainer()->get('currency.repository');
    }

    /**
     * @dataProvider dataTestHandlingTaxFreeInStorefront
     *
     * @param array<string> $vatIds
     */
    public function testHandlingTaxFreeInStorefrontWithBaseCurrencyEuro(
        string $testCase,
        float $currencyTaxFreeFrom,
        bool $countryTaxFree,
        bool $countryCompanyTaxFree,
        float $countryTaxFreeFrom,
        float $countryCompanyTaxFreeFrom,
        int $quantity,
        ?array $vatIds = null,
        bool $checkVatIdPattern = true
    ): void {
        $this->createShippingMethod();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'shippingMethodId' => $this->ids->get('shipping'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));

        $this->createProduct();

        $countryId = Uuid::fromBytesToHex($this->getCountryIdByIso());

        $this->createCustomerAndLogin($countryId);

        if ($vatIds) {
            $this->customerRepository->update(
                [['id' => $this->ids->get('customer'), 'vatIds' => $vatIds]],
                Context::createDefaultContext()
            );
        }

        $this->currencyRepository->update([[
            'id' => Defaults::CURRENCY,
            'taxFreeFrom' => $currencyTaxFreeFrom,
        ]], Context::createDefaultContext());

        $this->updateCountry(
            $countryId,
            $countryTaxFree,
            $countryTaxFreeFrom,
            $countryCompanyTaxFree,
            $countryCompanyTaxFreeFrom,
            Defaults::CURRENCY,
            $checkVatIdPattern
        );

        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode([
                'items' => [
                    [
                        'id' => $this->ids->get('p1'),
                        'type' => 'product',
                        'referencedId' => $this->ids->get('p1'),
                        'quantity' => $quantity,
                    ],
                ],
            ])
        );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if ($testCase === 'tax-free') {
            static::assertEquals((500 * $quantity) + 10, $response['price']['totalPrice']);
        } else {
            static::assertEquals((550 * $quantity) + 11, $response['price']['totalPrice']);
        }
    }

    /**
     * @dataProvider dataTestHandlingTaxFreeInStorefront
     *
     * @param array<string> $vatIds
     */
    public function testHandlingTaxFreeInStorefrontWithBaseCurrencyCHF(
        string $testCase,
        float $currencyTaxFreeFrom,
        bool $countryTaxFree,
        bool $countryCompanyTaxFree,
        float $countryTaxFreeFrom,
        float $countryCompanyTaxFreeFrom,
        int $quantity,
        ?array $vatIds = null,
        bool $checkVatIdPattern = true
    ): void {
        $currencyId = Uuid::fromBytesToHex($this->getCurrencyIdByIso('CHF'));

        $this->createShippingMethod();
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'currencyId' => $currencyId,
            'shippingMethodId' => $this->ids->get('shipping'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));

        $this->createProduct();

        $countryId = Uuid::fromBytesToHex($this->getCountryIdByIso('CH'));

        $this->createCustomerAndLogin($countryId);

        if ($vatIds) {
            $this->customerRepository->update(
                [['id' => $this->ids->get('customer'), 'vatIds' => $vatIds]],
                Context::createDefaultContext()
            );
        }

        $this->currencyRepository->update([[
            'id' => $currencyId,
            'taxFreeFrom' => $currencyTaxFreeFrom,
        ]], Context::createDefaultContext());

        $this->updateCountry(
            $countryId,
            $countryTaxFree,
            $countryTaxFreeFrom,
            $countryCompanyTaxFree,
            $countryCompanyTaxFreeFrom,
            Defaults::CURRENCY,
            $checkVatIdPattern
        );

        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode([
                'items' => [
                    [
                        'id' => $this->ids->get('p1'),
                        'type' => 'product',
                        'referencedId' => $this->ids->get('p1'),
                        'quantity' => $quantity,
                    ],
                ],
            ])
        );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if ($testCase === 'tax-free') {
            static::assertEquals((550 * $quantity) + 11, $response['price']['totalPrice']);
        } else {
            static::assertEquals((605 * $quantity) + 12.1, $response['price']['totalPrice']);
        }
    }

    /**
     * @dataProvider dataTestHandlingTaxFreeInStorefrontWithCountryBaseCurrencyUSD
     */
    public function testHandlingTaxFreeInStorefrontWithCountryBaseCurrencyUSD(
        string $testCase,
        bool $countryTaxFree,
        bool $countryCompanyTaxFree,
        float $countryTaxFreeFrom,
        float $countryCompanyTaxFreeFrom,
        int $quantity
    ): void {
        $currencyId = Uuid::fromBytesToHex($this->getCurrencyIdByIso('USD'));

        $this->createShippingMethod();
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'currencyId' => $currencyId,
            'shippingMethodId' => $this->ids->get('shipping'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));

        $this->createProduct();

        $usCountryId = Uuid::fromBytesToHex($this->getCountryIdByIso('US'));

        $this->createCustomerAndLogin($usCountryId);

        $this->updateCountry(
            $usCountryId,
            $countryTaxFree,
            $countryTaxFreeFrom,
            $countryCompanyTaxFree,
            $countryCompanyTaxFreeFrom,
            $currencyId
        );

        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode([
                'items' => [
                    [
                        'id' => $this->ids->get('p1'),
                        'type' => 'product',
                        'referencedId' => $this->ids->get('p1'),
                        'quantity' => $quantity,
                    ],
                ],
            ])
        );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if ($testCase === 'tax-free') {
            static::assertEquals(FloatComparator::cast((585.43 * $quantity) + 11.71), $response['price']['totalPrice']);
        } else {
            static::assertEquals(FloatComparator::cast((643.97 * $quantity) + 12.88), $response['price']['totalPrice']);
        }
    }

    /**
     * string $testCase
     * bool $countryTaxFree
     * bool $countryCompanyTaxFree
     * float $countryTaxFreeFrom
     * float $countryCompanyTaxFreeFrom
     * int $quantity
     *
     * @return array<mixed>
     */
    public static function dataTestHandlingTaxFreeInStorefrontWithCountryBaseCurrencyUSD(): array
    {
        return [
            'case 1 tax-free' => ['tax-free', true, false, 100, 100, 1],
            'case 2 tax-free' => ['tax-free', true, false, 1000, 100, 2],
            'case 3 tax-free' => ['tax-free', true, true, 1000, 100, 1],
            'case 4 tax-free' => ['tax-free', true, true, 1000, 1000, 2],
            'case 5 no-tax-free' => ['no-tax-free', true, false, 1000, 100, 1],
            'case 6 no-tax-free' => ['no-tax-free', true, true, 1000, 1000, 1],
            'case 7 no-tax-free' => ['no-tax-free', false, false, 1000, 1000, 1],
            'case 8 no-tax-free' => ['no-tax-free', false, false, 1000, 1000, 2],
            'case 9 tax-free' => ['tax-free', false, true, 100, 100, 1],
            'case 10 tax-free' => ['tax-free', false, true, 100, 1000, 2],
            'case 11 tax-free' => ['tax-free', true, true, 100, 1000, 1],
        ];
    }

    /**
     * string $testCase
     * float $currencyTaxFreeFrom
     * bool $countryTaxFree
     * bool $countryCompanyTaxFree
     * float $countryTaxFreeFrom
     * float $countryCompanyTaxFreeFrom
     * int $quantity
     * ?array vatIds
     * ?bool checkVatIdPattern
     *
     * @return array<array<mixed>>
     */
    public static function dataTestHandlingTaxFreeInStorefront(): array
    {
        return [
            'case 1 tax-free' => ['tax-free', 500, false, false, 0, 0, 1],
            'case 2 tax-free' => ['tax-free', 1000, false, false, 0, 0, 2],
            'case 3 no tax-free' => ['no tax-free', 1000, false, false, 0, 0, 1],
            'case 4 no tax-free' => ['no tax-free', 1000, true, false, 100, 0,  1],
            'case 5 no tax-free' => ['no tax-free', 1000, true, true, 100, 0, 1],
            'case 6 no tax-free' => ['no tax-free', 1000, false, true, 100, 0, 1],
            'case 7 no tax-free' => ['no tax-free', 1000, false, true, 100, 100, 1],
            'case 8 no tax-free' => ['no tax-free', 1000, true, false, 100, 100,  1],
            'case 9 no tax-free' => ['no tax-free', 1000, true, true, 100, 100, 1],
            'case 10 tax-free' => ['tax-free', 0, true, true, 100, 100, 1],
            'case 11 tax-free' => ['tax-free', 0, false, true, 100, 100, 1],
            'case 12 tax-free' => ['tax-free', 0, false, true, 0, 100, 1],
            'case 13 tax-free' => ['tax-free', 0, false, true, 1000, 100, 1],
            'case 14 tax-free' => ['tax-free', 0, true, false, 100, 100, 1],
            'case 15 tax-free' => ['tax-free', 0, true, false, 100, 1000, 1],
            'case 16 tax-free' => ['tax-free', 0, true, false, 100, 0, 1],
            'case 17 tax-free' => ['tax-free', 0, true, false, 1000, 0, 2],
            'case 18 tax-free' => ['tax-free', 0, false, true, 0, 1000, 2],
            'case 19 tax-free' => ['tax-free', 0, false, true, 0, 999.99, 3],
            'case 20 tax-free' => ['tax-free', 0, true, false, 1000, 0, 3],
            'case 21 no tax-free' => ['no tax-free', 0, true, true, 1000, 100, 1, ['DE1234567890123'], true],
            'case 22 tax-free' => ['tax-free', 0, false, true, 1000, 100, 1, ['DE1234567890123'], false],
            'case 23 tax-free' => ['tax-free', 0, false, true, 1000, 100, 1, ['DE123456789'], false],
            'case 24 tax-free' => ['tax-free', 0, false, true, 1000, 100, 1, ['DE123456789'], true],
        ];
    }

    private function createProduct(): void
    {
        $this->productRepository->create([
            [
                'id' => $this->ids->create('p1'),
                'productNumber' => $this->ids->get('p1'),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 550, 'net' => 500, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 10, 'name' => 'standard'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());
    }

    private function createCustomerAndLogin(string $countryId, ?string $email = null, ?string $password = null): void
    {
        $email ??= Uuid::randomHex() . '@example.com';
        $password ??= 'shopware';
        $this->createCustomer($countryId, $password, $email);

        $this->login($email, $password);
    }

    private function login(?string $email = null, ?string $password = null): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'email' => $email,
                    'password' => $password,
                ], \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    private function createCustomer(string $countryId, string $password, ?string $email = null): void
    {
        $this->customerRepository->create([
            [
                'id' => $this->ids->create('customer'),
                'salesChannelId' => $this->ids->get('sales-channel'),
                'defaultShippingAddress' => [
                    'id' => $this->ids->create('address'),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $countryId,
                ],
                'defaultBillingAddressId' => $this->ids->get('address'),
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
                'vatIds' => ['DE123456789'],
                'company' => 'Test',
            ],
        ], Context::createDefaultContext());
    }

    private function updateCountry(
        string $countryId,
        bool $countryTaxFree,
        float $countryTaxFreeFrom,
        bool $countryCompanyTaxFree,
        float $countryCompanyTaxFreeFrom,
        string $currencyId = Defaults::CURRENCY,
        bool $checkVatIdPattern = true
    ): void {
        $this->countryRepository->update([[
            'id' => $countryId,
            'customerTax' => [
                'enabled' => $countryTaxFree,
                'currencyId' => $currencyId,
                'amount' => $countryTaxFreeFrom,
            ],
            'companyTax' => [
                'enabled' => $countryCompanyTaxFree,
                'currencyId' => $currencyId,
                'amount' => $countryCompanyTaxFreeFrom,
            ],
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => $checkVatIdPattern,
        ]], Context::createDefaultContext());
    }

    private function getCountryIdByIso(string $iso = 'DE'): string
    {
        return $this->connection->fetchOne('SELECT id FROM country WHERE iso = :iso', ['iso' => $iso]);
    }

    private function getCurrencyIdByIso(string $iso = 'EUR'): string
    {
        return $this->connection->fetchOne('SELECT id FROM currency WHERE iso_code = :iso', ['iso' => $iso]);
    }

    private function createShippingMethod(): void
    {
        $data = [
            [
                'id' => $this->ids->create('shipping'),
                'active' => true,
                'bindShippingfree' => false,
                'name' => 'test',
                'availabilityRule' => [
                    'id' => $this->ids->create('rule'),
                    'name' => 'asd',
                    'priority' => 2,
                ],
                'deliveryTime' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'testDeliveryTime',
                    'min' => 1,
                    'max' => 90,
                    'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
                ],
                'prices' => [
                    [
                        'name' => 'Test',
                        'price' => 10,
                        'currencyId' => Defaults::CURRENCY,
                        'calculation' => 1,
                        'quantityStart' => 1,
                        'currencyPrice' => [
                            [
                                'currencyId' => Defaults::CURRENCY,
                                'net' => 10,
                                'gross' => 11,
                                'linked' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('shipping_method.repository')
            ->create($data, Context::createDefaultContext());
    }
}
