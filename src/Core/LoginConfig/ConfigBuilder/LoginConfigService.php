<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\ConfigBuilder;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\LoginConfig\ConfigBuilder\Handler\AbstractLoginConfigHandler;
use Shopware\Core\LoginConfig\ConfigBuilder\TemplateData\TemplateDataCollection;
use Shopware\Core\LoginConfig\LoginConfigException;

/**
 * @internal
 */
#[Package('core')]
class LoginConfigService
{
    /**
     * @var array<string, AbstractLoginConfigHandler>
     */
    private array $loginConfigHandlers = [];

    /**
     * @param array<string, mixed> $loginConfig
     */
    public function __construct(
        private readonly array $loginConfig,
    ) {
    }

    public function addHandler(AbstractLoginConfigHandler $handler, string $key): void
    {
        $this->loginConfigHandlers[$key] = $handler;
    }

    public function createTemplateData(): TemplateDataCollection
    {
        $templateDataCollection = new TemplateDataCollection();
        foreach ($this->loginConfig['sso_providers'] as $key => $configArray) {
            $loginConfigItem = LoginConfigItem::fromArray($key, $configArray);

            $handler = $this->loginConfigHandlers[$loginConfigItem->configKey] ?? null;
            if ($handler === null) {
                throw LoginConfigException::handlerNotFound($loginConfigItem->configKey);
            }

            $templateDataCollection->addTemplateData($handler->createTemplateData($loginConfigItem));
        }

        return $templateDataCollection;
    }

    /**
     * @return array{useDefault: bool, ssoProviders: array<array{key: string, snippet_key: string, icon: string, class: string, url: string}>}
     */
    public function templateDataToArray(TemplateDataCollection $templateDataCollection): array
    {
        $data = [
            'useDefault' => (bool) $this->loginConfig['use_default'],
            'ssoProviders' => [],
        ];
        foreach ($templateDataCollection as $templateData) {
            $data['ssoProviders'][] = $templateData->toArray();
        }

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
