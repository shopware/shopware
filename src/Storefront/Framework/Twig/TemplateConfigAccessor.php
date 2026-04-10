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
            if (!str_starts_with($script, 'js/components/')) {
                $scripts[] = $script;
            }
        }

        return $scripts;
    }

    /**
     * @return array<string, mixed>
     */
    public function componentImportMap(): array
    {
        $componentImportMap = [];
        $themeScripts = $this->themeScripts->getThemeScripts();

        // Filter theme scripts to component scripts only.
        $componentScripts = array_filter($themeScripts, function ($script) {
            return str_contains($script, 'js/components/');
        });

        // Create import map based on component tag.
        foreach ($componentScripts as $componentScript) {
            $componentTag = $this->getComponentTagFromScriptPath($componentScript);
            $componentImportMap[$componentTag] = $this->packages->getUrl($componentScript, 'theme');
        }

        return $componentImportMap;
    }

    /**
     * Derives the component tag from the script path.
     * Example: js/components/Sw/Product/BuyButton.js => Sw:Product:BuyButton
     * Example: js/components/Sw/Product/Detail/Reviews/index.js => Sw:Product:Detail:Reviews
     */
    private function getComponentTagFromScriptPath(string $path): string
    {
        $tag = str_replace('js/components/', '', $path);
        $tag = str_replace('.js', '', $tag);
        $tag = str_replace('/', ':', $tag);

        if (str_ends_with($tag, ':index')) {
            $tag = substr($tag, 0, -\strlen(':index'));
        }

        return $tag;
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
