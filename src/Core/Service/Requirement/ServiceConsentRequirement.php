<?php declare(strict_types=1);

namespace Shopware\Core\Service\Requirement;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Permission\PermissionsService;

/**
 * @internal
 *
 * This requirement gates the privilege granting step. When the general service consent is not accepted, privileges are not granted.
 */
#[Package('framework')]
class ServiceConsentRequirement implements ServiceRequirement
{
    public const NAME = 'service_consent';

    public function __construct(
        private readonly PermissionsService $permissionsService,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getGate(): Gate
    {
        return Gate::PRIVILEGES;
    }

    public function isSatisfied(): bool
    {
        return $this->permissionsService->areGranted();
    }

    public function permitsStateChange(): bool
    {
        return true;
    }
}
