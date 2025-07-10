<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\ServiceRegistry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Service\Message\LogPermissionToRegistryMessage;
use Shopware\Core\Service\Permission\ConsentState;
use Shopware\Core\Service\Permission\PermissionsConsent;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceRegistry\PermissionLogger;
use Shopware\Core\Service\ServiceRegistry\SaveConsentRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(PermissionLogger::class)]
class PermissionLoggerTest extends TestCase
{
    private Client&MockObject $client;

    private MessageBusInterface&MockObject $messageBus;

    private ShopIdProvider&MockObject $shopIdProvider;

    private SystemConfigService&MockObject $systemConfigService;

    private PermissionLogger $permissionLogger;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);

        $this->permissionLogger = new PermissionLogger(
            $this->client,
            $this->messageBus,
            $this->shopIdProvider,
            $this->systemConfigService
        );
    }

    public function testLogDispatchesMessageToMessageBus(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-user-id',
            grantedAt: new \DateTime('2025-06-13 12:00:00')
        );

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function (LogPermissionToRegistryMessage $message) use ($consent) {
                return $message->permissionsConsent === $consent && $message->consentState === ConsentState::GRANTED;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->permissionLogger->log($consent, ConsentState::GRANTED);
    }

    public function testLogSyncSavesConsentWhenStateIsGranted(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-user-id',
            grantedAt: new \DateTime('2025-06-13 12:00:00')
        );

        $shopId = 'test-shop-id';
        $licenseHost = 'https://example.com';

        $this->shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willReturn($shopId);

        $this->systemConfigService
            ->expects($this->once())
            ->method('getString')
            ->with(PermissionLogger::CONFIG_STORE_LICENSE_HOST)
            ->willReturn($licenseHost);

        $this->client
            ->expects($this->once())
            ->method('saveConsent')
            ->with(static::callback(function (SaveConsentRequest $request) use ($consent, $shopId, $licenseHost) {
                return $request->identifier === $consent->identifier
                    && $request->consentingUserId === $consent->consentingUserId
                    && $request->shopIdentifier === $shopId
                    && $request->consentDate === $consent->grantedAt->format(\DateTime::ATOM)
                    && $request->consentRevision === $consent->revision
                    && $request->licenseHost === $licenseHost;
            }));

        $this->permissionLogger->logSync($consent, ConsentState::GRANTED);
    }

    public function testLogSyncRevokesConsentWhenStateIsRevoked(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-user-id',
            grantedAt: new \DateTime('2025-06-13 12:00:00')
        );

        $this->shopIdProvider
            ->expects($this->never())
            ->method('getShopId');

        $this->systemConfigService
            ->expects($this->never())
            ->method('getString');

        $this->client
            ->expects($this->once())
            ->method('revokeConsent')
            ->with($consent->identifier);

        $this->permissionLogger->logSync($consent, ConsentState::REVOKED);
    }

    public function testLogSyncWithEmptyLicenseHost(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-user-id',
            grantedAt: new \DateTime('2025-06-13 12:00:00')
        );

        $shopId = 'test-shop-id';

        $this->shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willReturn($shopId);

        $this->systemConfigService
            ->expects($this->once())
            ->method('getString')
            ->with(PermissionLogger::CONFIG_STORE_LICENSE_HOST)
            ->willReturn('');

        $this->client
            ->expects($this->once())
            ->method('saveConsent')
            ->with(static::callback(function (SaveConsentRequest $request) use ($consent, $shopId) {
                return $request->identifier === $consent->identifier
                    && $request->consentingUserId === $consent->consentingUserId
                    && $request->shopIdentifier === $shopId
                    && $request->consentDate === $consent->grantedAt->format(\DateTime::ATOM)
                    && $request->consentRevision === $consent->revision
                    && $request->licenseHost === '';
            }));

        $this->permissionLogger->logSync($consent, ConsentState::GRANTED);
    }

    public function testLogWithRevokedState(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-user-id',
            grantedAt: new \DateTime('2025-06-13 12:00:00')
        );

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function (LogPermissionToRegistryMessage $message) use ($consent) {
                return $message->permissionsConsent === $consent && $message->consentState === ConsentState::REVOKED;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->permissionLogger->log($consent, ConsentState::REVOKED);
    }
}
