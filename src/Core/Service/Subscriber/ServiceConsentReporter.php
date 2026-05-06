<?php declare(strict_types=1);

namespace Shopware\Core\Service\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceRegistry\RevokeConsentRequest;
use Shopware\Core\Service\ServiceRegistry\SaveConsentRequest;
use Shopware\Core\System\Consent\Definition\ServiceConsent;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[Package('framework')]
class ServiceConsentReporter implements EventSubscriberInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsentAcceptedEvent::class => 'reportAcceptedConsent',
            ConsentRevokedEvent::class => 'reportRevokedConsent',
        ];
    }

    public function reportAcceptedConsent(ConsentAcceptedEvent $event): void
    {
        if ($event->consentName !== ServiceConsent::NAME || $event->revision === null) {
            return;
        }

        try {
            $this->client->saveConsent(new SaveConsentRequest(
                consentName: $event->consentName,
                consentingUserId: $event->actor,
                shopIdentifier: $this->shopIdProvider->getShopId()->id,
                consentDate: (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                consentRevision: $event->revision,
                licenseHost: $this->systemConfigService->getString(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN),
            ));
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to report accepted services consent to registry', ['exception' => $exception]);
        }
    }

    public function reportRevokedConsent(ConsentRevokedEvent $event): void
    {
        if ($event->consentName !== ServiceConsent::NAME) {
            return;
        }

        try {
            $this->client->revokeConsent(new RevokeConsentRequest(
                consentName: $event->consentName,
                shopIdentifier: $this->shopIdProvider->getShopId()->id,
                licenseHost: $this->systemConfigService->getString(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN),
            ));
        } catch (\Throwable $exception) {
            $this->logger->warning('Failed to report revoked services consent to registry', ['exception' => $exception]);
        }
    }
}
