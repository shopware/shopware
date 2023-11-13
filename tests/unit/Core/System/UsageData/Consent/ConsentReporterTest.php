<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Consent;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\System\UsageData\Consent\ConsentReporter;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\Consent\ConsentReporter
 */
#[Package('merchant-services')]
class ConsentReporterTest extends TestCase
{
    public function testReportConsentAddsShopIdHeader(): void
    {
        $httpClient = new MockHttpClient([
            static function ($method, $url, $options): MockResponse {
                static::assertContains('Shopware-Shop-Id: shopId', $options['headers']);

                return new MockResponse('', ['http_code' => 204]);
            },
        ]);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willReturn('shopId');

        $reporter = new ConsentReporter(
            $httpClient,
            $shopIdProvider,
            new StaticSystemConfigService(),
            $this->createMock(InstanceService::class),
        );

        $reporter->reportConsent(ConsentState::REQUESTED);
    }

    public function testReportConsentAddsShopIdToPayload(): void
    {
        $httpClient = new MockHttpClient([
            static function ($method, $url, $options): MockResponse {
                self::assertPayloadContains('shop_id', 'shopId', $options['body']);

                return new MockResponse('', ['http_code' => 204]);
            },
        ]);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->method('getShopId')
            ->willReturn('shopId');

        $reporter = new ConsentReporter(
            $httpClient,
            $shopIdProvider,
            new StaticSystemConfigService(),
            $this->createMock(InstanceService::class),
        );

        $reporter->reportConsent(ConsentState::REQUESTED);
    }

    public function testReportConsentAddsContentStateToPayload(): void
    {
        $httpClient = new MockHttpClient([
            static function ($method, $url, $options): MockResponse {
                self::assertPayloadContains('consent_state', ConsentState::REQUESTED->value, $options['body']);

                return new MockResponse('', ['http_code' => 204]);
            },
        ]);

        $reporter = new ConsentReporter(
            $httpClient,
            $this->createMock(ShopIdProvider::class),
            new StaticSystemConfigService(),
            $this->createMock(InstanceService::class),
        );

        $reporter->reportConsent(ConsentState::REQUESTED);
    }

    public function testReportConsentAddsShopwareVersionToPayload(): void
    {
        $httpClient = new MockHttpClient([
            static function ($method, $url, $options): MockResponse {
                self::assertPayloadContains('shopware_version', '6.5.0.0', $options['body']);

                return new MockResponse('', ['http_code' => 204]);
            },
        ]);

        $instanceService = $this->createMock(InstanceService::class);
        $instanceService->method('getShopwareVersion')
            ->willReturn('6.5.0.0');

        $reporter = new ConsentReporter(
            $httpClient,
            $this->createMock(ShopIdProvider::class),
            new StaticSystemConfigService(),
            $instanceService,
        );

        $reporter->reportConsent(ConsentState::REQUESTED);
    }

    public function testReportConsentAddsLicenseHostToPayload(): void
    {
        $httpClient = new MockHttpClient([
            static function ($method, $url, $options): MockResponse {
                self::assertPayloadContains('license_host', 'licenseHost', $options['body']);

                return new MockResponse('', ['http_code' => 204]);
            },
        ]);

        $reporter = new ConsentReporter(
            $httpClient,
            $this->createMock(ShopIdProvider::class),
            new StaticSystemConfigService([
                StoreRequestOptionsProvider::CONFIG_KEY_STORE_LICENSE_DOMAIN => 'licenseHost',
            ]),
            $this->createMock(InstanceService::class),
        );

        $reporter->reportConsent(ConsentState::REQUESTED);
    }

    public function testReportConsentAddsApiCredentialToPayload(): void
    {
        $httpClient = new MockHttpClient([
            static function ($method, $url, $options): MockResponse {
                self::assertPayloadContains(
                    'api_credential',
                    [
                        'app_url' => 'APP_URL',
                        'access_key' => '4cc3ss-k3y',
                        'secret_access_key' => 's3cr3t-4cc3ss-k3y',
                    ],
                    $options['body']
                );

                return new MockResponse('', ['http_code' => 204]);
            },
        ]);

        $reporter = new ConsentReporter(
            $httpClient,
            $this->createMock(ShopIdProvider::class),
            new StaticSystemConfigService(),
            $this->createMock(InstanceService::class),
        );

        $accessKeys = [
            'appUrl' => 'APP_URL',
            'accessKey' => '4cc3ss-k3y',
            'secretAccessKey' => 's3cr3t-4cc3ss-k3y',
        ];

        $reporter->reportConsent(ConsentState::REQUESTED, $accessKeys);
    }

    public function testReportConsentIntegrationAppUrlChanged(): void
    {
        $httpClient = new MockHttpClient([
            static function ($method, $url, $options): MockResponse {
                self::assertPayloadContains(
                    'api_credential',
                    [
                        'app_url' => 'APP_URL',
                        'access_key' => '4cc3ss-k3y',
                        'secret_access_key' => 's3cr3t-4cc3ss-k3y',
                    ],
                    $options['body']
                );

                return new MockResponse('', ['http_code' => 204]);
            },
        ]);

        $reporter = new ConsentReporter(
            $httpClient,
            $this->createMock(ShopIdProvider::class),
            new StaticSystemConfigService(),
            $this->createMock(InstanceService::class),
        );

        $accessKeys = [
            'appUrl' => 'APP_URL',
            'accessKey' => '4cc3ss-k3y',
            'secretAccessKey' => 's3cr3t-4cc3ss-k3y',
        ];

        $reporter->reportConsentIntegrationAppUrlChanged('shopId', $accessKeys);
    }

    private static function assertPayloadContains(string $key, mixed $value, string $body): void
    {
        $payload = json_decode($body, true, \JSON_THROW_ON_ERROR);

        static::assertIsArray($payload);
        static::assertArrayHasKey($key, $payload);
        static::assertSame($value, $payload[$key]);
    }
}
