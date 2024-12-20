<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\ConfigBuilder\Handler;

use Shopware\Core\LoginConfig\ConfigBuilder\LoginConfigItem;
use Shopware\Core\LoginConfig\ConfigBuilder\TemplateData\TemplateData;
use Symfony\Component\String\ByteString;

class ShopwareSso extends AbstractLoginConfigHandler
{
    protected string $configKey = 'swsso';

    public function createTemplateData(LoginConfigItem $loginConfigItem): TemplateData
    {
        $random = ByteString::fromRandom(32)->toString();
        return new TemplateData(
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
