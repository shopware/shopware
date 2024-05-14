<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\System\UsageData\Consent\ConsentReporter;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\Consent\ConsentStateChangedEvent;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentReporter::class)]
class ConsentReporterTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertEquals([
            ConsentStateChangedEvent::class => 'reportConsent',
        ], ConsentReporter::getSubscribedEvents());
    }

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
            'APP_URL',
            true
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
            'APP_URL',
            true
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
            'APP_URL',
            true
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
            'APP_URL',
            true
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
            'APP_URL',
            true
        );

        $reporter->reportConsent(ConsentState::REQUESTED);
    }

    public function testReportConsentDoesNotSendRequestInDevEnvironment(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $reporter = new ConsentReporter(
            $httpClient,
            $this->createMock(ShopIdProvider::class),
            new StaticSystemConfigService(),
            $this->createMock(InstanceService::class),
            'APP_URL',
            false
        );

        $httpClient->expects(static::never())
            ->method('request');

        $reporter->reportConsent(ConsentState::REQUESTED);
    }

    private static function assertPayloadContains(string $key, mixed $value, string $body): void
    {
        $payload = json_decode($body, true, \JSON_THROW_ON_ERROR);

        static::assertIsArray($payload);
        static::assertArrayHasKey($key, $payload);
        static::assertSame($value, $payload[$key]);
    }
}
