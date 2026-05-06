<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceRegistry\RevokeConsentRequest;
use Shopware\Core\Service\ServiceRegistry\SaveConsentRequest;
use Shopware\Core\Service\Subscriber\ServiceConsentReporter;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(ServiceConsentReporter::class)]
class ServiceConsentReporterTest extends TestCase
{
    private Client&MockObject $client;

    private ShopIdProvider&MockObject $shopIdProvider;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
        $this->shopIdProvider->method('getShopId')->willReturn(ShopId::v2('shop-id'));
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            ConsentAcceptedEvent::class => 'reportAcceptedConsent',
            ConsentRevokedEvent::class => 'reportRevokedConsent',
        ], ServiceConsentReporter::getSubscribedEvents());
    }

    public function testReportsAcceptedServicesConsent(): void
    {
        $this->client->expects($this->once())
            ->method('saveConsent')
            ->with($this->callback(static function (SaveConsentRequest $request): bool {
                return $request->consentName === 'service_consent'
                    && $request->consentingUserId === 'actor'
                    && $request->shopIdentifier === 'shop-id'
                    && $request->consentRevision === '2026-05-05'
                    && $request->licenseHost === 'license-host.example';
            }));

        $reporter = $this->createReporter();
        $reporter->reportAcceptedConsent(new ConsentAcceptedEvent('service_consent', 'system', 'system', 'actor', '2026-05-05'));
    }

    public function testReportsRevokedServicesConsent(): void
    {
        $this->client->expects($this->once())
            ->method('revokeConsent')
            ->with($this->callback(static function (RevokeConsentRequest $request): bool {
                return $request->consentName === 'service_consent'
                    && $request->shopIdentifier === 'shop-id'
                    && $request->licenseHost === 'license-host.example';
            }));

        $reporter = $this->createReporter();
        $reporter->reportRevokedConsent(new ConsentRevokedEvent('service_consent', 'system', 'system', 'actor'));
    }

    public function testIgnoresAcceptedServicesConsentWithoutRevision(): void
    {
        $this->client->expects($this->never())->method('saveConsent');

        $reporter = $this->createReporter();
        $reporter->reportAcceptedConsent(new ConsentAcceptedEvent('service_consent', 'system', 'system', 'actor'));
    }

    public function testIgnoresOtherConsentNames(): void
    {
        $this->client->expects($this->never())->method('saveConsent');
        $this->client->expects($this->never())->method('revokeConsent');

        $reporter = $this->createReporter();
        $reporter->reportAcceptedConsent(new ConsentAcceptedEvent('other-consent', 'system', 'system', 'actor', '2026-05-05'));
        $reporter->reportRevokedConsent(new ConsentRevokedEvent('other-consent', 'system', 'system', 'actor'));
    }

    public function testSwallowsRegistryErrors(): void
    {
        $this->client->expects($this->once())
            ->method('saveConsent')
            ->willThrowException(new \RuntimeException('gateway down'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Failed to report accepted services consent to registry',
                $this->callback(static fn (array $context): bool => ($context['exception'] ?? null) instanceof \RuntimeException)
            );

        $reporter = $this->createReporter();
        $reporter->reportAcceptedConsent(new ConsentAcceptedEvent('service_consent', 'system', 'system', 'actor', '2026-05-05'));
    }

    private function createReporter(): ServiceConsentReporter
    {
        return new ServiceConsentReporter(
            $this->client,
            $this->shopIdProvider,
            new StaticSystemConfigService([
                StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN => 'license-host.example',
            ]),
            $this->logger,
        );
    }
}
