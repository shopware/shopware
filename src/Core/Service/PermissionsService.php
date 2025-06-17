<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
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
    ) {
    }

    public function grantPermissions(string $revision, Context $context): void
    {
        $grantedRevision = \DateTimeImmutable::createFromFormat('Y-m-d', $revision);
        if ($grantedRevision === false) {
            throw ServiceException::invalidPermissionsRevisionFormat($revision);
        }

        $this->systemConfigService->set(self::CONFIG_KEY_ACCEPTED_PERMISSIONS_REVISION, $grantedRevision->format(Defaults::STORAGE_DATE_FORMAT));

        $this->eventDispatcher->dispatch(new PermissionsGrantedEvent($grantedRevision, $context));
    }

    public function revokePermissions(Context $context): void
    {
        $this->systemConfigService->delete(self::CONFIG_KEY_ACCEPTED_PERMISSIONS_REVISION);

        $this->eventDispatcher->dispatch(new PermissionsRevokedEvent($context));
    }

    public function getAcceptedPermissionsRevision(): ?\DateTimeInterface
    {
        $acceptedRevision = $this->systemConfigService->getString(self::CONFIG_KEY_ACCEPTED_PERMISSIONS_REVISION);

        if ($acceptedRevision === '') {
            return null;
        }

        return \DateTimeImmutable::createFromFormat(Defaults::STORAGE_DATE_FORMAT, $acceptedRevision) ?: null;
    }
}
