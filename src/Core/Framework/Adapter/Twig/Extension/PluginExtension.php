<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Framework\Log\Package;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigTest;

#[Package('core')]
class PluginExtension extends AbstractExtension
{
    public function __construct(
        private readonly array $activePlugins
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_active_plugins', $this->getActivePlugins(...)),
        ];
    }

    public function getTests(): array
    {
        return [
            'plugin_active' => new TwigTest('plugin_active', $this->isPluginActive(...)),
        ];
    }

    public function getActivePlugins(): array
    {
        return array_column($this->activePlugins, 'name');
    }

    public function isPluginActive(string $pluginName): bool
    {
        return \in_array($pluginName, $this->getActivePlugins(), true);
    }
}
