<?php declare(strict_types=1);

namespace Shopware\Administration\LoginConfig\ConfigBuilder;

use Shopware\Administration\LoginConfig\ConfigBuilder\Handler\AbstractLoginConfigHandler;
use Shopware\Administration\LoginConfig\ConfigBuilder\TemplateData\ProviderTemplateData;
use Shopware\Administration\LoginConfig\LoginConfigException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class LoginConfigService
{
    /**
     * @var array<string, AbstractLoginConfigHandler>
     */
    private array $loginConfigHandlers;

    /**
     * @param array<string, mixed> $loginConfig
     * @param array<string, AbstractLoginConfigHandler> $handlers
     */
    public function __construct(
        private readonly array $loginConfig,
        iterable $handlers,
    ) {
        $this->loginConfigHandlers = $handlers instanceof \Traversable ? iterator_to_array($handlers) : $handlers;
    }

    public function addHandler(AbstractLoginConfigHandler $handler, string $key): void
    {
        $this->loginConfigHandlers[$key] = $handler;
    }

    /**
     * @return array{useDefault: bool, ssoProviders: array<ProviderTemplateData>}
     */
    public function createTemplateData(): array
    {
        $templateDataArray = [];
        foreach ($this->loginConfig['sso_providers'] as $key => $configArray) {
            $loginConfigItem = LoginConfigItem::fromArray($key, $configArray);

            $handler = $this->loginConfigHandlers[$loginConfigItem->configKey] ?? throw LoginConfigException::handlerNotFound($loginConfigItem->configKey);

            $templateDataArray[$key] = $handler->createTemplateData($loginConfigItem);
        }

        $data = [
            'useDefault' => (bool) $this->loginConfig['use_default'],
            'ssoProviders' => $templateDataArray,
        ];

        return $data;
    }

    public function createRedirectUrl(string $key, string $random): string
    {
        $handler = $this->loginConfigHandlers[$key] ?? null;
        if ($handler === null) {
            throw LoginConfigException::handlerNotFound($key);
        }

        return $handler->createRedirectUrl($this->getLoginConfigItemByKey($key), $random);
    }

    public function getLoginConfigItemByKey(string $key): LoginConfigItem
    {
        $loginConfigArray = $this->loginConfig['sso_providers'][$key] ?? throw LoginConfigException::configForKeyNotFound($key);

        return LoginConfigItem::fromArray($key, $loginConfigArray);
    }
}
