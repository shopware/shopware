<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\Builder;

use Shopware\Core\LoginConfig\Builder\Handler\AbstractLoginConfigHandler;

/**
 * @internal
 */
class LoginConfigBuilder
{
    public function __construct(
        private readonly array $loginConfig,
        private readonly iterable $loginConfigHandlers
    ) {}

    public function build(): array
    {
        $providers = [];

        foreach ($this->loginConfig['sso_providers'] as $key => $loginConfigItemArray) {
            $loginConfigItem = LoginConfigItem::fromArray($key, $loginConfigItemArray);

            $handler = $this->getHandler($loginConfigItem->getKey());
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

    private function getHandler(string $type): ?AbstractLoginConfigHandler
    {
        foreach ($this->loginConfigHandlers as $loginConfigHandler) {
            if ($loginConfigHandler->supports($type)) {
                return $loginConfigHandler;
            }
        }

        return null;
    }
}
