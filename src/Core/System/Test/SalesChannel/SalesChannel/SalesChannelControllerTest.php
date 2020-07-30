<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\SalesChannel;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

class SalesChannelControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;
    use AssertArraySubsetBehaviour;

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
        $client = $this->getSalesChannelBrowser();
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

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/currency');
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        static::assertArrayHasKey('data', $content);
        $ids = array_column($content['data'], 'id');
        static::assertContains($originalCurrency['id'], $ids);
    }

    public function testGetSalesChannelLanguages(): void
    {
        $originalLanguage = $this->addLanguage();

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/language');
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertArrayHasKey('data', $content);

        $ids = array_column($content['data'], 'id');
        static::assertContains($originalLanguage['id'], $ids);
    }

    public function testGetSalesChannelCountries(): void
    {
        $originalCountry = $this->addCountry();

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/country');
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertArrayHasKey('data', $content);

        $ids = array_column($content['data'], 'id');
        static::assertContains($originalCountry['id'], $ids);
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

        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/country-state', $body);
        $response = $this->getSalesChannelBrowser()->getResponse();
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

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/payment-method');
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertArrayHasKey('data', $content);
        $ids = array_column($content['data'], 'id');

        static::assertContains($originalPaymentMethod['id'], $ids);
    }

    public function testGetSalesChannelShippingMethods(): void
    {
        $originalShippingMethod = $this->addShippingMethod();

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/shipping-method');
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertArrayHasKey('data', $content);
        $ids = array_column($content['data'], 'id');

        static::assertContains($originalShippingMethod['id'], $ids);
    }

    public function testGetSalesChannelShippingMethodsWithoutUnavailable(): void
    {
        $originalShippingMethod = $this->addShippingMethod();
        $this->addUnavailableShippingMethod();

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/shipping-method');
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertArrayHasKey('data', $content);

        $ids = array_column($content['data'], 'id');
        static::assertContains($originalShippingMethod['id'], $ids);
    }

    public function testGetMultiSalesChannelShippingMethods(): void
    {
        $originalShippingMethod = $this->addShippingMethod();
        $this->addShippingMethod();

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/shipping-method');
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertGreaterThanOrEqual(1, count($content['data']));
        static::assertCount($content['total'], $content['data']);

        $ids = array_column($content['data'], 'id');
        static::assertContains($originalShippingMethod['id'], $ids);
    }

    public function testGetDefaultSalesChannelShippingMethod(): void
    {
        $this->addUnavailableShippingMethod();
        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/shipping-method');
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertCount(2, $content['data']);
        static::assertEquals(2, $content['total']);
    }

    public function testGetSalesChannelPaymentMethodsWithoutUnavailable(): void
    {
        $originalPaymentMethod = $this->addPaymentMethod();
        $this->addUnavailablePaymentMethod();

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/payment-method');
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        static::assertCount(6, $content['data'], print_r($content['data'], true));

        static::assertArrayHasKey('data', $content);
        $ids = array_column($content['data'], 'id');
        static::assertContains($originalPaymentMethod['id'], $ids);
    }

    public function testGetMultiSalesChannelPaymentMethods(): void
    {
        $originalPaymentMethod = $this->addPaymentMethod();
        $this->addPaymentMethod();

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/payment-method');
        $response = $this->getSalesChannelBrowser()->getResponse();
        $content = json_decode($response->getContent(), true);

        static::assertEquals(200, $response->getStatusCode(), print_r($content, true));

        static::assertGreaterThanOrEqual(5, count($content['data']));
        static::assertCount($content['total'], $content['data']);

        static::assertArrayHasKey('data', $content);
        $ids = array_column($content['data'], 'id');
        static::assertContains($originalPaymentMethod['id'], $ids);
    }

    public function testGetSalutations(): void
    {
        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/salutation');

        $response = $this->getSalesChannelBrowser()->getResponse();
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
            'isoCode' => 'FOO',
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
                ['id' => $this->getAvailableShippingMethod()->getId()],
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
                ['id' => $this->getAvailableShippingMethod()->getId()],
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
