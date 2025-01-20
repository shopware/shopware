<?php declare(strict_types=1);

namespace Shopware\Administration\LoginConfig\ConfigBuilder\Handler;

use Shopware\Administration\LoginConfig\ConfigBuilder\LoginConfigItem;
use Shopware\Administration\LoginConfig\ConfigBuilder\TemplateData\ProviderTemplateData;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
abstract class AbstractLoginConfigHandler
{
    protected string $configKey;

    public function __construct(
        protected readonly string $appUrl,
        protected readonly string $adminPath,
    ) {
    }

    abstract public function createTemplateData(LoginConfigItem $loginConfigItem): ProviderTemplateData;

    abstract public function createRedirectUrl(LoginConfigItem $loginConfigItem, string $random): string;

    public function supports(string $configKey): bool
    {
        return $configKey === $this->configKey;
    }

    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    protected function createButtonUrl(LoginConfigItem $loginConfigItem, string $random): string
    {
        return \sprintf('%s/%s/sso/auth?key=%s&rdm=%s', $this->appUrl, $this->adminPath, $loginConfigItem->configKey, $random);
    }
}
