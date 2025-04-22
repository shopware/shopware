<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Storefront;

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;

/**
 * @internal
 */
class ThemeRuntimeConfigTestService extends ThemeRuntimeConfigService
{
    /**
     * @var array<string, ThemeRuntimeConfig>
     */
    private array $configs = [];

    public function __construct(StorefrontPluginConfigurationCollection $configurationCollection)
    {
        foreach ($configurationCollection as $plugin) {
            if (!$plugin->getIsTheme()) {
                continue;
            }

            $this->configs[$plugin->getTechnicalName()] = ThemeRuntimeConfig::fromArray([
                'themeId' => Uuid::randomHex(),
                'technicalName' => $plugin->getTechnicalName(),
                'viewInheritance' => $plugin->getViewInheritance(),
            ]);
        }
    }

    public function getActiveThemeNames(): array
    {
        return array_keys($this->configs);
    }

    public function getRuntimeConfigByName(string $technicalName): ?ThemeRuntimeConfig
    {
        return $this->configs[$technicalName] ?? null;
    }
}
