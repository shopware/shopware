<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\ConfigBuilder\Handler;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\LoginConfig\ConfigBuilder\LoginConfigItem;
use Shopware\Core\LoginConfig\ConfigBuilder\TemplateData\TemplateData;

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

    abstract public function createTemplateData(LoginConfigItem $loginConfigItem): TemplateData;

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
