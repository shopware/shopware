<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\Builder\Handler;

use Shopware\Core\LoginConfig\Builder\LoginConfigItem;

/**
 * @internal
 */
class SwSsoLogin extends AbstractLoginConfigHandler
{
    public function createTemplateData(LoginConfigItem $loginConfigItem): array
    {
        return [
            'key' => $loginConfigItem->getKey(),
            'snippet_key' => $loginConfigItem->getSnippetKey(),
            'icon' => $loginConfigItem->getIcon(),
            'class' => $loginConfigItem->getClass(),
            'url' => $this->createButtonUrl($loginConfigItem),
            'additionalData' => $loginConfigItem->getAdditionalData(),
        ];
    }

    protected function getType(): string
    {
        return 'swsso';
    }

    private function createButtonUrl(LoginConfigItem $loginConfigItem): string
    {
        return \sprintf(
            '%s/oauth/authorize?client_id=%s&redirect_uri=%s&response_type=code&scope=openid&state=%s',
            $loginConfigItem->getBaseUrl(),
            $loginConfigItem->getClientId(),
            \urlencode($loginConfigItem->getRedirectUri()),
            \urlencode('random_string+state')
        );
    }
}
