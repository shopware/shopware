<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal (FEATURE_NEXT_14114)
 */
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
        Feature::skipTestIfInActive('FEATURE_NEXT_14114', $this);

        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->countryRepository = $this->getContainer()->get('country.repository');
        $this->currencyRepository = $this->getContainer()->get('currency.repository');

        $this->createTestData();
    }

    /**
     * @dataProvider dataTestHandlingTaxFreeInStorefront
     */
    public function testHandlingTaxFreeInStorefront(
        string $testCase,
        float $currencyTaxFreeFrom,
        bool $countryTaxFree,
        bool $countryCompanyTaxFree,
        float $countryTaxFreeFrom,
        int $quantity
    ): void {
        $this->createCustomerAndLogin();

        $this->currencyRepository->update([[
            'id' => Defaults::CURRENCY,
            'taxFreeFrom' => $currencyTaxFreeFrom,
        ]], $this->ids->context);

        $this->countryRepository->update([[
            'id' => Uuid::fromBytesToHex($this->getCountryIdByIso()),
            'taxFree' => $countryTaxFree,
            'companyTaxFree' => $countryCompanyTaxFree,
            'taxFreeFrom' => $countryTaxFreeFrom,
        ]], $this->ids->context);

        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [
                'items' => [
                    [
                        'id' => $this->ids->get('p1'),
                        'type' => 'product',
                        'referencedId' => $this->ids->get('p1'),
                        'quantity' => $quantity,
                    ],
                ],
            ]
        );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true);

        if ($testCase === 'tax-free') {
            static::assertEquals(500 * $quantity, $response['price']['totalPrice']);
        } else {
            static::assertEquals(550, $response['price']['totalPrice']);
        }
    }

    public function dataTestHandlingTaxFreeInStorefront(): array
    {
        return [
            'case 1 tax-free' => ['tax-free', 100, false, false, 0, 1],
            'case 2 no tax-free' => ['no tax-free', 1000, false, false, 0, 1],
            'case 3 no tax-free' => ['no tax-free', 1000, true, false, 100, 1],
            'case 4 tax-free' => ['tax-free', 0, true, false, 100, 1],
            'case 5 no tax-free' => ['no tax-free', 0, true, false, 1000, 1],
            'case 6 tax-free' => ['tax-free', 0, false, true, 100, 1],
            'case 7 no tax-free' => ['no tax-free', 0, false, true, 1000, 1],
            'case 8 tax-free' => ['tax-free', 100, true, false, 0, 1],
            'case 9 tax-free' => ['tax-free', 100, true, false, 100, 1],
            'case 10 tax-free' => ['tax-free', 100, false, true, 0, 1],
            'case 11 tax-free' => ['tax-free', 100, false, true, 100, 1],
            'case 12 tax-free' => ['tax-free', 1000, false, false, 0, 2],
            'case 13 tax-free' => ['tax-free', 0, false, true, 1000, 2],
            'case 14 tax-free' => ['tax-free', 0, true, false, 1000, 2],
            'case 15 no tax-free' => ['no tax-free', 1000, true, false, 10000, 1],
            'case 16 no tax-free' => ['no tax-free', 1000, true, true, 100, 1],
            'case 17 tax-free' => ['no tax-free', 0, true, true, 1000, 1],
        ];
    }

    private function createTestData(): void
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
        ], $this->ids->context);
    }

    private function createCustomerAndLogin(?string $email = null, ?string $password = null): void
    {
        $email = $email ?? (Uuid::randomHex() . '@example.com');
        $password = $password ?? 'shopware';
        $this->createCustomer($password, $email);

        $this->login($email, $password);
    }

    private function login(?string $email = null, ?string $password = null): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);
    }

    private function createCustomer(string $password, ?string $email = null): void
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
                    'countryId' => Uuid::fromBytesToHex($this->getCountryIdByIso()),
                ],
                'defaultBillingAddressId' => $this->ids->get('address'),
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
                'vatIds' => ['DE123456789'],
                'company' => 'Test',
            ],
        ], $this->ids->context);
    }

    private function getCountryIdByIso(string $iso = 'DE'): string
    {
        return $this->connection->fetchOne('SELECT id FROM country WHERE iso = :iso', ['iso' => $iso]);
    }
}
