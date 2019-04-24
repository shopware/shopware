<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\SalesChannel;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Content\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;

class SalesChannelControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour,
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
        $client = $this->getSalesChannelClient();
        $client->setServerParameter('CONTENT_TYPE', 'application/json');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $this->context = Context::createDefaultContext();

        // reset rules
        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CartRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);
    }

    public function testGetSalesChannelCurrencies(): void
    {
        $originalCurrency = $this->addCurrency();

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/currency');
        $response = $this->getSalesChannelClient()->getResponse();
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

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/language');
        $response = $this->getSalesChannelClient()->getResponse();
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

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/country');
        $response = $this->getSalesChannelClient()->getResponse();
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

        $body = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'country_state.countryId',
                    'value' => $countryId,
                ],
            ],
        ];

        $this->getSalesChannelClient()->request('POST', '/sales-channel-api/v1/country-state', $body);
        $response = $this->getSalesChannelClient()->getResponse();
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

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/payment-method');
        $response = $this->getSalesChannelClient()->getResponse();
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

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/shipping-method');
        $response = $this->getSalesChannelClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $shippingMethod) {
            if ($shippingMethod['id'] !== $originalShippingMethod['id']) {
                continue;
            }

            unset($originalShippingMethod['availabilityRules']);
            $this->silentAssertArraySubset($originalShippingMethod, $shippingMethod);
        }
    }

    public function testGetSalesChannelShippingMethodsWithoutUnavailable(): void
    {
        $originalShippingMethod = $this->addShippingMethod();
        $this->addUnavailableShippingMethod();

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/shipping-method');
        $response = $this->getSalesChannelClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $shippingMethod) {
            if ($shippingMethod['id'] !== $originalShippingMethod['id']) {
                continue;
            }

            unset($originalShippingMethod['availabilityRules']);
            $this->silentAssertArraySubset($originalShippingMethod, $shippingMethod);
        }
    }

    public function testGetMultiSalesChannelShippingMethods(): void
    {
        $originalShippingMethod = $this->addShippingMethod();
        $this->addShippingMethod();

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/shipping-method');
        $response = $this->getSalesChannelClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertGreaterThanOrEqual(1, count($content['data']));
        static::assertCount($content['total'], $content['data']);

        foreach ($content['data'] as $shippingMethod) {
            if ($shippingMethod['id'] !== $originalShippingMethod['id']) {
                continue;
            }

            unset($originalShippingMethod['availabilityRules']);
            $this->silentAssertArraySubset($originalShippingMethod, $shippingMethod);
        }
    }

    public function testGetDefaultSalesChannelShippingMethod(): void
    {
        $this->addUnavailableShippingMethod();
        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/shipping-method');
        $response = $this->getSalesChannelClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertCount(2, $content['data']);
        static::assertEquals(2, $content['total']);
    }

    public function testGetSalesChannelPaymentMethodsWithoutUnavailable(): void
    {
        $originalPaymentMethod = $this->addPaymentMethod();
        $this->addUnavailablePaymentMethod();

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/payment-method');
        $response = $this->getSalesChannelClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertCount(7, $content['data'], print_r($content['data'], true));

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

        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/payment-method');
        $response = $this->getSalesChannelClient()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(200, $response->getStatusCode(), print_r($content, true));

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

    public function testGetSalutations(): void
    {
        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/salutation');

        $response = $this->getSalesChannelClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertGreaterThanOrEqual(3, count($content['data']));
        static::assertCount($content['total'], $content['data']);

        foreach ($content['data'] as $salutation) {
            static::assertArrayHasKey('salutationKey', $salutation);
        }
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
            'id' => Uuid::randomHex(),
            'factor' => 1.23,
            'decimalPrecision' => 2,
            'symbol' => 'USD',
            'position' => 10,
            'shortName' => 'USD',
            'name' => 'US Dollar',
        ];
        $data = [
            'id' => $this->getSalesChannelApiSalesChannelId(),
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
            'id' => Uuid::randomHex(),
            'name' => 'German',
            'locale' => [
                'id' => Uuid::randomHex(),
                'code' => 'x-tst_' . Uuid::randomHex(),
                'name' => 'test name',
                'territory' => 'test territory',
            ],
            'translationCode' => [
                'id' => Uuid::randomHex(),
                'code' => 'x-tst_' . Uuid::randomHex(),
                'name' => 'test name',
                'territory' => 'test',
            ],
        ];
        $data = [
            'id' => $this->getSalesChannelApiSalesChannelId(),
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
            'id' => Uuid::randomHex(),
            'name' => 'Germany',
        ];
        $data = [
            'id' => $this->getSalesChannelApiSalesChannelId(),
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
            'id' => Uuid::randomHex(),
            'name' => 'Germany',
            'states' => [
                [
                    'id' => Uuid::randomHex(),
                    'shortCode' => 'NRW',
                    'name' => 'Northrine westfalia',
                ],
                [
                    'id' => Uuid::randomHex(),
                    'shortCode' => 'HAM',
                    'name' => 'Hamburg',
                ],
            ],
        ];
        $data = [
            'id' => $this->getSalesChannelApiSalesChannelId(),
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
            'id' => Uuid::randomHex(),
            'name' => 'PayPal',
            'handlerIdentifier' => SyncTestPaymentHandler::class,
            'description' => 'My payment method',
            'availabilityRule' => [
                'id' => Uuid::randomHex(),
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
        ];

        $paymentMethods[] = $paymentMethod;
        $data = [
            'id' => $this->getSalesChannelApiSalesChannelId(),
            'paymentMethods' => $paymentMethods,
        ];
        $this->salesChannelRepository->update([$data], $this->context);
        unset($paymentMethod['availabilityRule']);

        return $paymentMethod;
    }

    private function addShippingMethod(): array
    {
        $shippingMethod = [
            'id' => Uuid::randomHex(),
            'name' => 'Express shipping',
            'bindShippingfree' => false,
            'deliveryTime' => $this->createDeliveryTimeData(),
            'availabilityRule' => [
                'id' => Uuid::randomHex(),
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
        ];
        $data = [
            'id' => $this->getSalesChannelApiSalesChannelId(),
            'shippingMethods' => [
                ['id' => $this->getAvailableShippingMethodId()],
                $shippingMethod,
            ],
        ];
        $this->salesChannelRepository->update([$data], $this->context);
        unset($shippingMethod['availabilityRule']);

        return $shippingMethod;
    }

    private function addUnavailableShippingMethod(): array
    {
        $shippingMethod = [
            'id' => Uuid::randomHex(),
            'name' => 'Special shipping',
            'bindShippingfree' => false,
            'deliveryTime' => $this->createDeliveryTimeData(),
            'availabilityRule' => ['name' => 'test', 'priority' => 0],
        ];
        $data = [
            'id' => $this->getSalesChannelApiSalesChannelId(),
            'shippingMethods' => [
                ['id' => $this->getAvailableShippingMethodId()],
                $shippingMethod,
            ],
        ];
        $this->salesChannelRepository->update([$data], $this->context);

        return $shippingMethod;
    }

    private function addUnavailablePaymentMethod(): array
    {
        $paymentMethod = [
            'id' => Uuid::randomHex(),
            'handlerIdentifier' => SyncTestPaymentHandler::class,
            'name' => 'Special payment',
            'position' => 4,
            'active' => true,
            'availabilityRule' => [
                'id' => Uuid::randomHex(),
                'name' => 'Rule',
                'priority' => 100,
                'conditions' => [
                    [
                        'type' => 'cartCartAmount',
                        'value' => [
                            'operator' => '=',
                            'amount' => 0,
                        ],
                    ],
                    [
                        'type' => 'cartCartAmount',
                        'value' => [
                            'operator' => '!=',
                            'amount' => 0,
                        ],
                    ],
                ],
            ],
        ];
        $paymentMethodId = $this->getValidPaymentMethodId();
        $data = [
            'id' => $this->getSalesChannelApiSalesChannelId(),
            'paymentMethodId' => $paymentMethodId,
            'paymentMethods' => [
                ['id' => $paymentMethodId],
                $paymentMethod,
            ],
        ];
        $this->salesChannelRepository->update([$data], $this->context);

        return $paymentMethod;
    }

    private function createDeliveryTimeData(): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'min' => 1,
            'max' => 90,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
        ];
    }
}
