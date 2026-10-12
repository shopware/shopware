<?php declare(strict_types=1);

namespace Shopware\Core\Service\Requirement;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 *
 * This requirement gates the installation step. When services are disabled, the installation is skipped.
 */
#[Package('framework')]
class ServicesEnabledRequirement implements ServiceRequirement
{
    public const NAME = 'services_enabled';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public static function getName(): string
    {
        return self::NAME;
    }

    public function getGate(): Gate
    {
        return Gate::INSTALLATION;
    }

    public function isSatisfied(): bool
    {
        return !$this->systemConfigService->getBool(LifecycleManager::CONFIG_KEY_SERVICES_DISABLED);
    }

    public function permitsStateChange(): bool
    {
        return true;
    }
}
