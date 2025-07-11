<?php declare(strict_types=1);

namespace Shopware\Core\Service\Permission;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Shopware\Core\Service\ServiceException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class PermissionsService
{
    private const CONFIG_KEY_PERMISSIONS_CONSENT = 'core.services.permissionsConsent';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RemoteLogger $remoteConsentLogger,
    ) {
    }

    public function grant(string $revision, Context $context): void
    {
        $source = $context->getSource();
        if (!($source instanceof AdminApiSource) || $source->getUserId() === null) {
            throw ServiceException::invalidPermissionsContext();
        }

        $grantedRevision = \DateTimeImmutable::createFromFormat('Y-m-d', $revision);
        if ($grantedRevision === false) {
            throw ServiceException::invalidPermissionsRevisionFormat($revision);
        }

        $consentIdentifier = Random::getAlphanumericString(32);
        $consentingUser = $source->getUserId();

        $consent = new PermissionsConsent(
            identifier: $consentIdentifier,
            revision: $revision,
            consentingUserId: $consentingUser,
            grantedAt: new \DateTime()
        );

        $this->systemConfigService->set(self::CONFIG_KEY_PERMISSIONS_CONSENT, json_encode($consent, \JSON_THROW_ON_ERROR));
        $this->remoteConsentLogger->log($consent, ConsentState::GRANTED);
        $this->eventDispatcher->dispatch(new PermissionsGrantedEvent($consent, $context));
    }

    /**
     * @throws ServiceException
     */
    public function revoke(Context $context): void
    {
        $consent = $this->fetchConsent();
        // either a valid consent exists or it does not. we can safely delete the config key anyway.
        $this->systemConfigService->delete(self::CONFIG_KEY_PERMISSIONS_CONSENT);

        if ($consent !== null) {
            // a valid consent exists, log the revocation
            $this->remoteConsentLogger->log($consent, ConsentState::REVOKED);
            $this->eventDispatcher->dispatch(new PermissionsRevokedEvent($consent, $context));
        }
    }

    public function areGranted(): bool
    {
        return $this->fetchConsent() !== null;
    }

    private function fetchConsent(): ?PermissionsConsent
    {
        $revision = $this->systemConfigService->getString(self::CONFIG_KEY_PERMISSIONS_CONSENT);
        if ($revision === '') {
            return null;
        }

        try {
            return PermissionsConsent::fromJsonString($revision);
        } catch (ServiceException) {
        }

        return null;
    }
}
