<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelProxyControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour,
        AssertArraySubsetBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    protected function setUp(): void
    {
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
    }

    public function testProxyWithInvalidSalesChannelId(): void
    {
        $this->getClient()->request('GET', $this->getUrl(Uuid::randomHex(), '/product'));

        $response = $this->getClient()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
        static::assertEquals('FRAMEWORK__INVALID_SALES_CHANNEL', $response['errors'][0]['code'] ?? null);
    }

    public function testProxyCallToSalesChannelApi(): void
    {
        $salesChannel = $this->createSalesChannel();

        $this->getClient()->request('GET', $this->getUrl($salesChannel['id'], '/product'));

        $response = $this->getClient()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayNotHasKey('errors', $response);
    }

    public function testHeadersAreCopied(): void
    {
        $salesChannel = $this->createSalesChannel();
        $uuid = Uuid::randomHex();

        $this->getClient()->request(
            'GET',
            $this->getUrl($salesChannel['id'], '/product'),
            [],
            [],
            [
                'HTTP_X_SW_CONTEXT_TOKEN' => $uuid,
                'HTTP_X_SW_LANGUAGE_ID' => $uuid,
                'HTTP_X_SW_VERSION_ID' => $uuid,
            ]
        );

        static::assertEquals($uuid, $this->getClient()->getRequest()->headers->get('x-sw-context-token'));
        static::assertEquals($uuid, $this->getClient()->getRequest()->headers->get('x-sw-language-id'));
        static::assertEquals($uuid, $this->getClient()->getRequest()->headers->get('x-sw-version-id'));
        static::assertEquals($uuid, $this->getClient()->getResponse()->headers->get('x-sw-context-token'));
        static::assertEquals($uuid, $this->getClient()->getResponse()->headers->get('x-sw-language-id'));
        static::assertEquals($uuid, $this->getClient()->getResponse()->headers->get('x-sw-version-id'));
    }

    public function testOnlyDefinedHeadersAreCopied(): void
    {
        $salesChannel = $this->createSalesChannel();

        $this->getClient()->request(
            'GET',
            $this->getUrl($salesChannel['id'], '/product'),
            [],
            [],
            [
                'HTTP_X_SW_CUSTOM_HEADER' => 'foo',
            ]
        );

        static::assertEquals('foo', $this->getClient()->getRequest()->headers->get('x-sw-custom-header'));
        static::assertArrayNotHasKey('x-sw-custom-header', $this->getClient()->getResponse()->headers->all());
    }

    public function testDifferentLanguage(): void
    {
        $langId = Uuid::randomHex();
        $salesChannel = $this->createSalesChannel();
        $this->createLanguage($langId, $salesChannel['id']);

        $this->assertTranslation(
            ['name' => 'not translated', 'translated' => ['name' => 'not translated']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            $salesChannel['id'],
            Defaults::LANGUAGE_SYSTEM
        );

        $this->assertTranslation(
            ['name' => 'translated', 'translated' => ['name' => 'translated']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            $salesChannel['id'],
            $langId
        );

        $this->assertTranslation(
            ['name' => 'translated', 'translated' => ['name' => 'translated']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            $salesChannel['id'],
            $langId
        );
    }

    private function getLangHeaderName(): string
    {
        return 'HTTP_' . strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
    }

    private function assertTranslation(array $expectedTranslations, array $data, string $salesChannelId, $langOverride = null): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';

        $categoryData = $data;
        $categoryData['active'] = true;
        if (!isset($categoryData['id'])) {
            $categoryData['id'] = Uuid::randomHex();
        }

        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $this->assertEntityExists($this->getClient(), 'category', $categoryData['id']);

        $headers = ['HTTP_ACCEPT' => 'application/json'];
        if ($langOverride) {
            $headers[$this->getLangHeaderName()] = $langOverride;
        }

        $this->getClient()->request('GET', $this->getUrl($salesChannelId, '/category/' . $categoryData['id']), [], [], $headers);

        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $responseData = json_decode($response->getContent(), true);

        static::assertArrayHasKey('data', $responseData, $response->getContent());

        $this->silentAssertArraySubset($expectedTranslations, $responseData['data']);
    }

    private function createLanguage($langId, string $salesChannelId, $fallbackId = null): void
    {
        $baseUrl = '/api/v' . PlatformRequest::API_VERSION;

        if ($fallbackId) {
            $fallbackLocaleId = Uuid::randomHex();
            $parentLanguageData = [
                'id' => $fallbackId,
                'name' => 'test language ' . $fallbackId,
                'locale' => [
                    'id' => $fallbackLocaleId,
                    'code' => 'x-tst_' . $fallbackLocaleId,
                    'name' => 'Test locale ' . $fallbackLocaleId,
                    'territory' => 'Test territory ' . $fallbackLocaleId,
                ],
                'translationCodeId' => $fallbackLocaleId,
            ];
            $this->getClient()->request('POST', $baseUrl . '/language', $parentLanguageData);
            static::assertEquals(204, $this->getClient()->getResponse()->getStatusCode());
        }

        $localeId = Uuid::randomHex();
        $languageData = [
            'id' => $langId,
            'name' => 'test language ' . $langId,
            'parentId' => $fallbackId,
            'locale' => [
                'id' => $localeId,
                'code' => 'x-tst_' . $localeId,
                'name' => 'Test locale ' . $localeId,
                'territory' => 'Test territory ' . $localeId,
            ],
            'translationCodeId' => $localeId,
            'salesChannels' => [
                ['id' => $salesChannelId],
            ],
        ];

        $this->getClient()->request('POST', $baseUrl . '/language', $languageData);
        static::assertEquals(204, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $this->getClient()->request('GET', $baseUrl . '/language/' . $langId);
    }

    private function getUrl(string $salesChannelId, string $url): string
    {
        return sprintf(
            '/api/v%d/_proxy/sales-channel-api/%s/v%1$d/%s',
            PlatformRequest::API_VERSION,
            $salesChannelId,
            ltrim($url, '/')
        );
    }

    private function createSalesChannel(array $salesChannel = []): array
    {
        $defaults = [
            'id' => Uuid::randomHex(),
            'name' => 'unit test channel',
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en_GB'),
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ];

        $salesChannel = array_merge_recursive($defaults, $salesChannel);

        $this->salesChannelRepository->create([$salesChannel], Context::createDefaultContext());

        return $salesChannel;
    }
}
