<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SalesChannel\Storefront;

use Doctrine\DBAL\Driver\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\StorefrontFunctionalTestBehaviour;

class StorefrontSalesChannelControllerTest extends TestCase
{
    use StorefrontFunctionalTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var Context
     */
    private $context;

    public function setUp()
    {
        $this->getStorefrontClient()->setServerParameter('CONTENT_TYPE', 'application/json');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
    }

    public function testGetSalesChannelCurrencies()
    {
        $originalCurrency = $this->addCurrency();

        $this->getStorefrontClient()->request('GET', '/storefront-api/sales-channel/currencies');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $currency) {
            if ($currency['id'] !== $originalCurrency['id']) {
                continue;
            }

            static::assertArraySubset($originalCurrency, $currency);

            return;
        }

        static::fail('Unable to find currency');
    }

    public function testGetSalesChannelLanguages()
    {
        $originalLanguage = $this->addLanguage();

        $this->getStorefrontClient()->request('GET', '/storefront-api/sales-channel/languages');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $language) {
            if ($language['id'] !== $originalLanguage['id']) {
                continue;
            }

            static::assertArraySubset($originalLanguage, $language);

            return;
        }

        static::fail('Unable to find language');
    }

    public function testGetSalesChannelCountries()
    {
        $originalCountry = $this->addCountry();

        $this->getStorefrontClient()->request('GET', '/storefront-api/sales-channel/countries');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $country) {
            if ($country['id'] !== $originalCountry['id']) {
                continue;
            }

            static::assertArraySubset($originalCountry, $country);

            return;
        }

        static::fail('Unable to find country');
    }

    public function testGetSalesChannelCountryStates()
    {
        $originalCountryWithStates = $this->addCountryWithStates();
        $countryId = $originalCountryWithStates['id'];

        $this->getStorefrontClient()->request('GET', '/storefront-api/sales-channel/country/states', ['countryId' => $countryId]);
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $states = $content['data'];

        $originalStates = $originalCountryWithStates['states'];

        $this->sortById($originalStates);
        $this->sortById($states);

        static::assertArraySubset($originalStates, $states);
    }

    public function testGetSalesChannelPaymentMethods()
    {
        $originalPaymentMethod = $this->addPaymentMethod();

        $this->getStorefrontClient()->request('GET', '/storefront-api/sales-channel/payment-methods');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $paymentMethod) {
            if (!$paymentMethod['id'] === $originalPaymentMethod['id']) {
                continue;
            }

            static::assertArraySubset($originalPaymentMethod, $paymentMethod);

            return;
        }

        static::fail('Unable to find payment method');
    }

    public function testGetSalesChannelShippingMethods()
    {
        $originalShippingMethod = $this->addShippingMethod();

        $this->getStorefrontClient()->request('GET', '/storefront-api/sales-channel/shipping-methods');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        foreach ($content['data'] as $shippingMethod) {
            if (!$shippingMethod['id'] === $originalShippingMethod['id']) {
                continue;
            }

            static::assertArraySubset($originalShippingMethod, $shippingMethod);

            return;
        }

        static::fail('Unable to find shipping method');
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
            'isDefault' => false,
            'factor' => 1.23,
            'symbol' => 'USD',
            'placedInFront' => false,
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
            'localeId' => Defaults::LOCALE,
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
        $paymentMethod = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'PayPal',
            'technicalName' => Uuid::uuid4()->getHex(),
            'additionalDescription' => 'My payment method',
        ];
        $data = [
            'id' => $this->getStorefrontApiSalesChannelId(),
            'paymentMethods' => [
                $paymentMethod,
            ],
        ];
        $this->salesChannelRepository->update([$data], $this->context);

        return $paymentMethod;
    }

    private function addShippingMethod(): array
    {
        $shippingMethod = [
            'id' => Uuid::uuid4()->getHex(),
            'name' => 'Express shipping',
            'type' => 1,
            'bindShippingfree' => false,
        ];
        $data = [
            'id' => $this->getStorefrontApiSalesChannelId(),
            'shippingMethods' => [
                $shippingMethod,
            ],
        ];
        $this->salesChannelRepository->update([$data], $this->context);

        return $shippingMethod;
    }
}
