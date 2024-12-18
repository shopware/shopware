<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\Builder;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\LoginConfig\Builder\Handler\AbstractLoginConfigHandler;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @internal
 */
#[Package('core')]
class LoginConfigBuilder
{
    /**
     * @param array<string, mixed> $loginConfig
     * @param iterable<AbstractLoginConfigHandler> $loginConfigHandlers
     */
    public function __construct(
        private readonly array $loginConfig,
        private readonly iterable $loginConfigHandlers
    ) {
    }

    /**
     * @return array{useDefault: bool, ssoProviders: list<array{key: string, snippet_key: string, icon: string, class: string, url: string}>}
     */
    public function build(SessionInterface $session): array
    {
        $providers = [];

        foreach ($this->loginConfig['sso_providers'] as $key => $loginConfigItemArray) {
            $loginConfigItem = LoginConfigItem::fromArray($key, $loginConfigItemArray);

            $handler = $this->getHandler($loginConfigItem->key, $session);
            if ($handler === null) {
                continue;
            }

            $providers[] = $handler->createTemplateData($loginConfigItem)->toArray();
        }

        return [
            'useDefault' => (bool) $this->loginConfig['use_default'],
            'ssoProviders' => $providers,
        ];
    }

    private function getHandler(string $type, SessionInterface $session): ?AbstractLoginConfigHandler
    {
        foreach ($this->loginConfigHandlers as $loginConfigHandler) {
            if ($loginConfigHandler->supports($type)) {
                $loginConfigHandler->setSession($session);

                return $loginConfigHandler;
            }
        }

        return null;
    }
}
