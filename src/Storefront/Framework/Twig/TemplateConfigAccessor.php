<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;
use Shopware\Storefront\Theme\ThemeScripts;
use Symfony\Component\Asset\Packages;

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
        private readonly Packages $packages,
    ) {
    }

    /**
     * @return string|bool|array<mixed>|float|int|null
     */
    public function config(string $key, ?string $salesChannelId)
    {
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
     * @return array<int, string> $items
     */
    public function scripts(): array
    {
        $scripts = [];

        foreach ($this->themeScripts->getThemeScripts() as $script) {
            $scripts[] = $script;
        }

        return $scripts;
    }

    /**
     * Returns the full import map data: top-level imports and optional scoped imports for extensions.
     *
     * The stored map contains theme-relative paths; this method converts them to full URLs using
     * the Symfony asset Packages service so that the browser receives absolute or CDN-prefixed URLs.
     *
     * Shape:
     * [
     *   'imports' => ['Sw:Product:Listing' => 'https://...', 'shopware' => 'https://...', ...],
     *   'scopes'  => ['/theme/prefix/js/components/MyPlugin/' => ['some-lib' => 'https://...']],
     * ]
     *
     * `scopes` is omitted when no extension vendor chunks are present.
     *
     * @return array{imports: array<string, string>, scopes?: array<string, array<string, string>>}
     */
    public function componentImportMap(): array
    {
        $stored = $this->themeScripts->getComponentImportMap();

        if ($stored === null) {
            return ['imports' => []];
        }

        $imports = array_map(
            fn (string $path): string => $this->packages->getUrl($path, 'theme'),
            $stored['imports']
        );

        $scopes = [];
        foreach ($stored['scopes'] ?? [] as $scopePath => $entries) {
            $scopeUrl = $this->packages->getUrl($scopePath, 'theme');
            $scopes[$scopeUrl] = array_map(
                fn (string $path): string => $this->packages->getUrl($path, 'theme'),
                $entries
            );
        }

        $result = ['imports' => $imports];

        if ($scopes !== []) {
            $result['scopes'] = $scopes;
        }

        return $result;
    }

    /**
     * @return array<string, int|string|bool> $items
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
