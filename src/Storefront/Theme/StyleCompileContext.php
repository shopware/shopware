<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

class StyleCompileContext extends Struct
{
    /**
     * @var string
     */
    protected $variables;

    /**
     * @var string
     */
    protected $concatenatedStyles;

    /**
     * @var StorefrontPluginConfiguration
     */
    protected $themeConfig;

    /**
     * @var array
     */
    protected $resolveMappings;

    /**
     * @var string
     */
    protected $salesChannelId;

    public function __construct(
        string $variables,
        string $concatenatedStyles,
        StorefrontPluginConfiguration $themeConfig,
        array $resolveMappings,
        string $salesChannelId
    ) {
        $this->variables = $variables;
        $this->concatenatedStyles = $concatenatedStyles;
        $this->themeConfig = $themeConfig;
        $this->resolveMappings = $resolveMappings;
        $this->salesChannelId = $salesChannelId;
    }

    public function getVariables(): string
    {
        return $this->variables;
    }

    public function setVariables(string $variables): void
    {
        $this->variables = $variables;
    }

    public function getConcatenatedStyles(): string
    {
        return $this->concatenatedStyles;
    }

    public function setConcatenatedStyles(string $concatenatedStyles): void
    {
        $this->concatenatedStyles = $concatenatedStyles;
    }

    public function getFullStyles(): string
    {
        return $this->variables . $this->concatenatedStyles;
    }

    public function getThemeConfig(): StorefrontPluginConfiguration
    {
        return $this->themeConfig;
    }

    public function setThemeConfig(StorefrontPluginConfiguration $themeConfig): void
    {
        $this->themeConfig = $themeConfig;
    }

    public function getResolveMappings(): array
    {
        return $this->resolveMappings;
    }

    public function setResolveMappings(array $resolveMappings): void
    {
        $this->resolveMappings = $resolveMappings;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }
}
