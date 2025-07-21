<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso;

use Shopware\Administration\Login\Config\LoginConfig;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class SsoService
{
    public function __construct(
        private readonly LoginConfigService $loginConfigService,
    ) {
    }

    public function isSso(): bool
    {
        return $this->loginConfigService->getConfig() instanceof LoginConfig;
    }
}
