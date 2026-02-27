<?php declare(strict_types=1);

namespace Shopware\Core\Service\Requirement;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Permission\PermissionsService;

/**
 * @internal
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

    public function isSatisfied(): bool
    {
        return $this->permissionsService->areGranted();
    }
}
