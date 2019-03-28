<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Storefront;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Context\CheckoutRuleLoader;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\StorefrontFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Storefront\Test\Page\StorefrontPageTestConstants;

class StorefrontSalesChannelControllerTest extends TestCase
{
    use StorefrontFunctionalTestBehaviour,
        AssertArraySubsetBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $client = $this->getStorefrontClient();
        $client->setServerParameter('CONTENT_TYPE', 'application/json');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $this->context = Context::createDefaultContext();

        // reset rules
        $ruleLoader = $this->getContainer()->get(CheckoutRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CheckoutRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);
    }

    public function testGetSalesChannelCurrencies(): void
    {
        $originalCurrency = $this->addCurrency();

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/currency');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $currency) {
            if ($currency['id'] !== $originalCurrency['id']) {
                continue;
            }

            $this->silentAssertArraySubset($originalCurrency, $currency);

            return;
        }

        static::fail('Unable to find currency');
    }

    public function testGetSalesChannelLanguages(): void
    {
        $originalLanguage = $this->addLanguage();

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/language');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $language) {
            if ($language['id'] !== $originalLanguage['id']) {
                continue;
            }

            $this->silentAssertArraySubset($originalLanguage, $language);

            return;
        }

        static::fail('Unable to find language');
    }

    public function testGetSalesChannelCountries(): void
    {
        $originalCountry = $this->addCountry();

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/country');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $country) {
            if ($country['id'] !== $originalCountry['id']) {
                continue;
            }

            $this->silentAssertArraySubset($originalCountry, $country);

            return;
        }

        static::fail('Unable to find country');
    }

    public function testGetSalesChannelCountryStates(): void
    {
        $originalCountryWithStates = $this->addCountryWithStates();
        $countryId = $originalCountryWithStates['id'];

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/country/' . $countryId . '/state');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $states = $content['data'];

        $originalStates = $originalCountryWithStates['states'];

        $this->sortById($originalStates);
        $this->sortById($states);

        $this->silentAssertArraySubset($originalStates, $states);
    }

    public function testGetSalesChannelPaymentMethods(): void
    {
        $originalPaymentMethod = $this->addPaymentMethod();

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/payment-method');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $paymentMethod) {
            if ($paymentMethod['id'] !== $originalPaymentMethod['id']) {
                continue;
            }

            $this->silentAssertArraySubset($originalPaymentMethod, $paymentMethod);

            return;
        }

        static::fail('Unable to find payment method');
    }

    public function testGetSalesChannelShippingMethods(): void
    {
        $originalShippingMethod = $this->addShippingMethod();

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/shipping-method');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $shippingMethod) {
            if ($shippingMethod['id'] !== $originalShippingMethod['id']) {
                continue;
            }

            $this->silentAssertArraySubset($originalShippingMethod, $shippingMethod);

            return;
        }

        static::fail('Unable to find shipping method');
    }

    public function testGetSalesChannelShippingMethodsWithoutUnavailable(): void
    {
        $originalShippingMethod = $this->addShippingMethod();
        $this->addUnavailableShippingMethod();

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/shipping-method');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $shippingMethod) {
            if ($shippingMethod['id'] !== $originalShippingMethod['id']) {
                continue;
            }

            $this->silentAssertArraySubset($originalShippingMethod, $shippingMethod);

            return;
        }

        static::fail('Unable to find shipping method');
    }

    public function testGetMultiSalesChannelShippingMethods(): void
    {
        $originalShippingMethod = $this->addShippingMethod();
        $this->addShippingMethod();

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/shipping-method');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertGreaterThanOrEqual(3, count($content['data']));
        static::assertCount($content['total'], $content['data']);

        foreach ($content['data'] as $shippingMethod) {
            if ($shippingMethod['id'] !== $originalShippingMethod['id']) {
                continue;
            }

            $this->silentAssertArraySubset($originalShippingMethod, $shippingMethod);

            return;
        }

        static::fail('Unable to find shipping method');
    }

    public function testGetDefaultSalesChannelShippingMethod(): void
    {
        $this->addUnavailableShippingMethod();
        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/shipping-method');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertCount(1, $content['data']);
        static::assertEquals(1, $content['total']);

        static::assertSame(Defaults::SHIPPING_METHOD, $content['data'][0]['id']);
    }

    public function testGetSalesChannelPaymentMethodsWithoutUnavailable(): void
    {
        $originalPaymentMethod = $this->addPaymentMethod();
        $this->addUnavailablePaymentMethod();

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/payment-method');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertCount(StorefrontPageTestConstants::PAYMENT_METHOD_COUNT + 1, $content['data'], print_r($content['data'], true));

        foreach ($content['data'] as $paymentMethod) {
            if ($paymentMethod['id'] !== $originalPaymentMethod['id']) {
                continue;
            }

            $this->silentAssertArraySubset($originalPaymentMethod, $paymentMethod);

            return;
        }

        static::fail('Unable to find payment method');
    }

    public function testGetMultiSalesChannelPaymentMethods(): void
    {
        $originalPaymentMethod = $this->addPaymentMethod();
        $this->addPaymentMethod();

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/payment-method');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertGreaterThanOrEqual(5, count($content['data']));
        static::assertCount($content['total'], $content['data']);

        foreach ($content['data'] as $shippingMethod) {
            if ($shippingMethod['id'] !== $originalPaymentMethod['id']) {
                continue;
            }

            $this->silentAssertArraySubset($originalPaymentMethod, $shippingMethod);

            return;
        }

        static::fail('Unable to find payment method');
    }

    private function sortById(&$array): void
    {
        usort($array, function ($a, $b) {
            return strcmp($a['id'], $b['id']);
        });
    }

    private function addCurrency(): array
    {
        $currency = [
            'id' => Uuid::uuid4()->getHex(),
            'factor' => 1.23,
            'decimalPrecision' => 2,
            'symbol' => 'USD',
            'position' => 10,
            'shortName' => 'USD',
            'name' => 'US Dollar',
        ];
        $data = [
            'id' => $this->getStorefrontApiSalesChannelId(),
            'currencies' => [
                $currency,
            ],
        ];
        $this->salesChannelRepository->update([$data], $this->context);

        return $currency;
    }

    private function addLanguage(): array
    {
        $language = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'German',
            'locale' => [
                'id' => Uuid::uuid4()->getHex(),
                'code' => 'x-tst_' . Uuid::uuid4()->getHex(),
                'name' => 'test name',
                'territory' => 'test territory',
            ],
            'translationCode' => [
                'id' => Uuid::uuid4()->getHex(),
                'code' => 'x-tst_' . Uuid::uuid4()->getHex(),
                'name' => 'test name',
                'territory' => 'test',
            ],
        ];
        $data = [
            'id' => $this->getStorefrontApiSalesChannelId(),
            'languages' => [
                $language,
            ],
        ];

        $this->salesChannelRepository->update([$data], $this->context);

        return $language;
    }

    private function addCountry(): array
    {
        $country = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'Germany',
        ];
        $data = [
            'id' => $this->getStorefrontApiSalesChannelId(),
            'countries' => [
                $country,
            ],
        ];
        $this->salesChannelRepository->update([$data], $this->context);

        return $country;
    }

    private function addCountryWithStates(): array
    {
        $country = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'Germany',
            'states' => [
                [
                    'id' => Uuid::uuid4()->getHex(),
                    'shortCode' => 'NRW',
                    'name' => 'Northrine westfalia',
                ],
                [
                    'id' => Uuid::uuid4()->getHex(),
                    'shortCode' => 'HAM',
                    'name' => 'Hamburg',
                ],
            ],
        ];
        $data = [
            'id' => $this->getStorefrontApiSalesChannelId(),
            'countries' => [
                $country,
            ],
        ];
        $this->salesChannelRepository->update([$data], $this->context);

        return $country;
    }

    private function addPaymentMethod(): array
    {
        $connection = $this->getContainer()->get(Connection::class);
        $paymentMethods = $connection->executeQuery('SELECT id FROM payment_method')->fetchAll(FetchMode::COLUMN);
        $paymentMethods = array_map(function (string $id) {
            return ['id' => Uuid::fromBytesToHex($id)];
        }, $paymentMethods);

        $paymentMethod = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'PayPal',
            'handlerIdentifier' => SyncTestPaymentHandler::class,
            'description' => 'My payment method',
            'availabilityRules' => [
                [
                    'id' => Uuid::uuid4()->getHex(),
                    'name' => 'Rule',
                    'priority' => 100,
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
            ],
        ];

        $paymentMethods[] = $paymentMethod;
        $data = [
            'id' => $this->getStorefrontApiSalesChannelId(),
            'paymentMethods' => $paymentMethods,
        ];
        $this->salesChannelRepository->update([$data], $this->context);
        unset($paymentMethod['availabilityRules']);

        return $paymentMethod;
    }

    private function addShippingMethod(): array
    {
        $shippingMethod = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'Express shipping',
            'bindShippingfree' => false,
            'availabilityRules' => [
                [
                    'id' => Uuid::uuid4()->getHex(),
                    'name' => 'Rule',
                    'priority' => 100,
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
            ],
        ];
        $data = [
            'id' => $this->getStorefrontApiSalesChannelId(),
            'shippingMethods' => [
                ['id' => Defaults::SHIPPING_METHOD],
                $shippingMethod,
            ],
        ];
        $this->salesChannelRepository->update([$data], $this->context);
        unset($shippingMethod['availabilityRules']);

        return $shippingMethod;
    }

    private function addUnavailableShippingMethod(): array
    {
        $shippingMethod = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'Special shipping',
            'bindShippingfree' => false,
        ];
        $data = [
            'id' => $this->getStorefrontApiSalesChannelId(),
            'shippingMethods' => [
                ['id' => Defaults::SHIPPING_METHOD],
                $shippingMethod,
            ],
        ];
        $this->salesChannelRepository->update([$data], $this->context);

        return $shippingMethod;
    }

    private function addUnavailablePaymentMethod(): array
    {
        $paymentMethod = [
            'id' => Uuid::uuid4()->getHex(),
            'handlerIdentifier' => SyncTestPaymentHandler::class,
            'name' => 'Special payment',
            'position' => 4,
            'active' => true,
        ];
        $paymentMethodId = $this->getValidPaymentMethodId();
        $data = [
            'id' => $this->getStorefrontApiSalesChannelId(),
            'paymentMethodId' => $paymentMethodId,
            'paymentMethods' => [
                ['id' => $paymentMethodId],
                $paymentMethod,
            ],
        ];
        $this->salesChannelRepository->update([$data], $this->context);

        return $paymentMethod;
    }
}
