<?php declare(strict_types=1);

namespace Shopware\Core\Service\Permission;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
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
    private const CONFIG_KEY_ACCEPTED_PERMISSIONS_REVISION = 'core.services.acceptedPermissionsRevision';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RemoteLogger $remoteConsentLogger,
    ) {
    }

    public function grantPermissions(string $revision, Context $context): void
    {
        $source = $context->getSource();
        if (!($source instanceof AdminApiSource) || $source->getUserId() === null) {
            throw ServiceException::invalidPermissionsContext();
        }

        $grantedRevision = \DateTimeImmutable::createFromFormat('Y-m-d', $revision);
        if ($grantedRevision === false) {
            throw ServiceException::invalidPermissionsRevisionFormat($revision);
        }

        $revisionValue = $grantedRevision->format(\DateTimeInterface::ATOM);
        $consentIdentifier = bin2hex(random_bytes(16));
        $consentingUser = $source->getUserId();

        $consent = new PermissionsConsent(
            identifier: $consentIdentifier,
            revision: $revisionValue,
            consentingUserId: $consentingUser,
            grantedAt: new \DateTime()
        );

        $this->systemConfigService->set(self::CONFIG_KEY_ACCEPTED_PERMISSIONS_REVISION, json_encode($consent, \JSON_THROW_ON_ERROR));
        $this->remoteConsentLogger->log($consent, ConsentState::GRANTED);
        $this->eventDispatcher->dispatch(new PermissionsGrantedEvent($consent, $context));
    }

    /**
     * @throws ServiceException
     */
    public function revokePermissions(Context $context): void
    {
        $consent = PermissionsConsent::fromJsonString($this->systemConfigService->getString(self::CONFIG_KEY_ACCEPTED_PERMISSIONS_REVISION));
        $this->systemConfigService->delete(self::CONFIG_KEY_ACCEPTED_PERMISSIONS_REVISION);

        $this->remoteConsentLogger->log($consent, ConsentState::REVOKED);
        $this->eventDispatcher->dispatch(new PermissionsRevokedEvent($consent, $context));
    }
}
