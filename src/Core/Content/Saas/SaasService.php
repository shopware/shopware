<?php declare(strict_types=1);

namespace Shopware\Core\Content\Saas;

use Shopware\Administration\Login\Config\LoginConfig;
use Shopware\Administration\Login\Config\LoginConfigService;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class SaasService
{
    public function __construct(
        private readonly LoginConfigService $loginConfigService,
    ) {
    }

    public function isSaas(): bool
    {
        return $this->loginConfigService->getConfig() instanceof LoginConfig;
    }
}
