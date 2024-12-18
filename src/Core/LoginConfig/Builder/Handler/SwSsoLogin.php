<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\Builder\Handler;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\LoginConfig\Builder\LoginConfigItem;
use Shopware\Core\LoginConfig\Builder\TemplateData;
use Symfony\Component\String\ByteString;

/**
 * @internal
 */
#[Package('core')]
class SwSsoLogin extends AbstractLoginConfigHandler
{
    public function __construct(
        private readonly string $appUrl,
        private readonly string $admin,
    ) {
    }

    public function createTemplateData(LoginConfigItem $loginConfigItem): TemplateData
    {
        return new TemplateData(
            $loginConfigItem->key,
            $loginConfigItem->snippetKey ?? '',
            $loginConfigItem->icon ?? '',
            $loginConfigItem->class ?? '',
            $this->createButtonUrl($loginConfigItem),
        );
    }

    protected function getType(): string
    {
        return 'swsso';
    }

    private function createButtonUrl(LoginConfigItem $loginConfigItem): string
    {
        $sessionKey = \sprintf('SSO_%s', $loginConfigItem->key);
        $sessionValue = \sprintf('%s/%s+%s', $this->appUrl, $this->admin, ByteString::fromRandom(32)->toString());

        $this->getSession()->set($sessionKey, $sessionValue);

        return \sprintf(
            '%s/oauth/authorize?client_id=%s&redirect_uri=%s&response_type=code&scope=openid&state=%s',
            $loginConfigItem->baseUrl,
            $loginConfigItem->clientId,
            \urlencode($loginConfigItem->redirectUri ?? ''),
            \urlencode($sessionValue)
        );
    }
}
