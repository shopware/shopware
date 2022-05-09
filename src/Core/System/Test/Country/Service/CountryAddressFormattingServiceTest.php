<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Country\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Service\CountryAddressFormattingService;
use Shopware\Core\System\Country\Struct\CountryAddress;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class CountryAddressFormattingServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ?StringTemplateRenderer $templateRenderer;

    private ?CountryAddressFormattingService $countryAddressFormattingService;

    private ?EntityRepositoryInterface $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->countryAddressFormattingService = $this->getContainer()->get(CountryAddressFormattingService::class);
        $this->templateRenderer = $this->getContainer()->get(StringTemplateRenderer::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');
    }

    /**
     * @dataProvider dataProviderTestRender
     */
    public function testRender(array $address, ?string $template, string $expected): void
    {
        $actual = $this->countryAddressFormattingService->render(
            CountryAddress::createFromEntityJsonSerialize($address),
            $template,
            Context::createDefaultContext(),
        );

        static::assertEquals($expected, $actual);
    }

    public function dataProviderTestRender(): \Generator
    {
        yield 'render correctly' => [
            [
                'firstName' => 'Duy',
                'lastName' => 'Dinh',
                'street' => 'abc',
                'city' => 'Vietnam',
                'zipcode' => '55000',
            ],
            "{{firstName}}\n{{lastName}}",
            "Duy\nDinh",
        ];

        yield 'prevent render if template is null' => [
            [
                'firstName' => 'Duy',
                'lastName' => 'Dinh',
                'street' => 'abc',
                'city' => 'Vietnam',
                'zipcode' => '55000',
            ],
            null,
            '',
        ];

        yield 'prevent empty line if the variable is null' => [
            [
                'firstName' => 'Duy',
                'lastName' => 'Dinh',
                'company' => null,
                'street' => 'abc',
                'city' => 'Vietnam',
                'zipcode' => '55000',
            ],
            "{{firstName}}\n{{company}}\n{{lastName}}",
            "Duy\nDinh",
        ];
    }

    private function createOrder(Context $context, array $customData = []): string
    {
        $orderId = Uuid::randomHex();
        $billingAddressId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        $order = array_merge([
            'id' => $orderId,
            'stateId' => Uuid::randomHex(),
            'orderNumber' => Uuid::randomHex(),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'email' => 'test@example.com',
                'firstName' => 'Noe',
                'lastName' => 'Hill',
                'salutationId' => $this->getValidSalutationId(),
                'title' => 'Doc',
                'customerNumber' => 'Test',
                'customer' => [
                    'id' => $customerId,
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'defaultShippingAddress' => [
                        'id' => $billingAddressId,
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'street' => 'Musterstraße 1',
                        'city' => 'Schoöppingen',
                        'zipcode' => '12345',
                        'salutationId' => $this->getValidSalutationId(),
                        'countryId' => $this->getValidCountryId(),
                    ],
                    'defaultBillingAddressId' => $billingAddressId,
                    'defaultPaymentMethod' => [
                        'name' => 'Invoice',
                        'active' => true,
                        'description' => 'Default payment method',
                        'handlerIdentifier' => SyncTestPaymentHandler::class,
                        'availabilityRule' => [
                            'id' => Uuid::randomHex(),
                            'name' => 'true',
                            'priority' => 0,
                            'conditions' => [
                                [
                                    'type' => 'cartCartAmount',
                                    'value' => [
                                        'operator' => '>=',
                                        'amount' => 0,
                                    ],
                                ],
                            ],
                        ],
                        'salesChannels' => [
                            [
                                'id' => TestDefaults::SALES_CHANNEL,
                            ],
                        ],
                    ],
                    'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                    'email' => 'abc@mail.com',
                    'password' => 'abc',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'salutationId' => $this->getValidSalutationId(),
                    'customerNumber' => '12345',
                ],
            ],
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingAddressId' => $billingAddressId,
            'addresses' => [
                [
                    'id' => $billingAddressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [
            ],
            'context' => '{}',
            'payload' => '{}',
        ], $customData);

        $this->orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function setUseDefaultFormatForCountry(): void
    {
        $this->getContainer()->get(Connection::class)
            ->executeUpdate('UPDATE `country` SET `use_default_address_format` = 0
                 WHERE id = :id', [
                'id' => Uuid::fromHexToBytes($this->getValidCountryId()),
            ]);
    }
}
