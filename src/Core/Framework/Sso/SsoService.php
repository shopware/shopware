<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Sso;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Sso\Config\LoginConfig;
use Shopware\Core\Framework\Sso\Config\LoginConfigService;

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
