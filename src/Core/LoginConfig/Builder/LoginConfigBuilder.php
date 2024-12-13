<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\Builder;

use Shopware\Core\LoginConfig\Builder\Handler\AbstractLoginConfigHandler;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @internal
 */
class LoginConfigBuilder
{
    public function __construct(
        private readonly array $loginConfig,
        private readonly iterable $loginConfigHandlers
    ) {}

    public function build(SessionInterface $session): array
    {
        $providers = [];

        foreach ($this->loginConfig['sso_providers'] as $key => $loginConfigItemArray) {
            $loginConfigItem = LoginConfigItem::fromArray($key, $loginConfigItemArray);

            $handler = $this->getHandler($loginConfigItem->key, $session);
            if ($handler === null) {
                continue;
            }

            $providers[] = $handler->createTemplateData($loginConfigItem);
        }

        return [
            'useDefault' => $this->loginConfig['use_default'],
            'providers' => $providers,
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
