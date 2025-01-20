<?php declare(strict_types=1);

namespace Shopware\Administration\LoginConfig\ConfigBuilder\Handler;

use Shopware\Administration\LoginConfig\ConfigBuilder\LoginConfigItem;
use Shopware\Administration\LoginConfig\ConfigBuilder\TemplateData\ProviderTemplateData;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\String\ByteString;

/**
 * @internal
 */
#[Package('core')]
class ShopwareSso extends AbstractLoginConfigHandler
{
    protected string $configKey = 'swsso';

    public function createTemplateData(LoginConfigItem $loginConfigItem): ProviderTemplateData
    {
        $random = ByteString::fromRandom(32)->toString();

        return new ProviderTemplateData(
            $loginConfigItem->configKey,
            $loginConfigItem->snippetKey,
            $loginConfigItem->icon,
            $loginConfigItem->class,
            $this->createButtonUrl($loginConfigItem, $random),
            $random
        );
    }

    public function createRedirectUrl(LoginConfigItem $loginConfigItem, string $random): string
    {
        $state = \sprintf('%s/api/oauth/sso/code?rdm=%s', $this->appUrl, $random);

        return \sprintf(
            '%s/oauth/authorize?client_id=%s&redirect_uri=%s&response_type=code&scope=openid&state=%s',
            $loginConfigItem->baseUrl,
            $loginConfigItem->clientId,
            \urlencode($loginConfigItem->redirectUri ?? ''),
            \urlencode($state)
        );
    }
}
