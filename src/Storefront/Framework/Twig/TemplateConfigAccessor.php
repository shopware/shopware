<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;
use Shopware\Storefront\Theme\ThemeScripts;

#[Package('framework')]
class TemplateConfigAccessor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly ThemeConfigValueAccessor $themeConfigAccessor,
        private readonly ThemeScripts $themeScripts,
        private readonly string $kernelEnvironment = 'prod',
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use SystemConfigService in PHP code instead. Twig code can continue using config().
     *
     * @return string|bool|array<mixed>|float|int|null
     */
    public function config(string $key, ?string $salesChannelId)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'SystemConfigService')
        );

        $static = $this->getStatic();

        if (\array_key_exists($key, $static)) {
            return $static[$key];
        }

        return $this->systemConfigService->get($key, $salesChannelId);
    }

    /**
     * @return string|bool|array<string, mixed>|float|int|null
     */
    public function theme(string $key, SalesChannelContext $context, ?string $themeId)
    {
        return $this->themeConfigAccessor->get($key, $context, $themeId);
    }

    /**
     * @return list<string> $items
     */
    public function scripts(): array
    {
        return array_values($this->themeScripts->getThemeScripts());
    }

    /**
     * Returns the full import map data: top-level imports, optional scoped imports for extensions,
     * and optional ordered lists of CSS and JS URLs.
     *
     * When the Vite component dev server is running it writes a flag file that
     * IS the complete map (all entries already contain full dev-server URLs).
     * That map is returned with `isDevServer: true` added so that the template
     * can treat dev-server component CSS as a replacement for the compiled theme
     * stylesheet (the dev server re-compiles component SCSS on the fly).
     *
     * In production the stored map already contains full URLs pre-computed at theme
     * compile time. The `styles` key,
     * if present, lists the component CSS files at public/storefront/components/ that must be
     * loaded alongside the regular compiled theme stylesheet.
     *
     * @return array{imports: array<string, string>, scopes?: array<string, array<string, string>>, styles?: list<string>, scripts?: list<string>, themeId?: string, isDevServer?: bool}
     */
    public function importMap(): array
    {
        // Vite dev server running: the flag file already provides the complete map.
        // Only active in the dev environment — never in production or test.
        if ($this->kernelEnvironment === 'dev') {
            $devMap = $this->themeScripts->getDevImportMap();
            if ($devMap !== null) {
                return $devMap + ['isDevServer' => true];
            }
        }

        return $this->themeScripts->getImportMap() ?? ['imports' => []];
    }

    /**
     * Returns all theme config fields that have `"scss": true` (the default) as a key/value
     * map so Twig can render custom properties with context-appropriate escaping.
     *
     * Delegates to ThemeConfigValueAccessor::getCssVarValues() so values are resolved
     * the same way as theme_config() — media URLs substituted, cached per sales channel.
     *
     * @return array<string, string|int>
     */
    public function themeCssVars(SalesChannelContext $context, ?string $themeId): array
    {
        return $this->themeConfigAccessor->getCssVarValues($context, $themeId);
    }

    /**
     * @return array<string, int|string|bool>
     */
    private function getStatic(): array
    {
        return [
            'seo.descriptionMaxLength' => 255,
            'cms.revocationNoticeCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
            'cms.taxCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
            'cms.tosCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
            'confirm.revocationNotice' => true,
        ];
    }
}
