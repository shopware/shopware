<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;
use Shopware\Storefront\Theme\ThemeScripts;
use Shopware\Storefront\Framework\Twig\Components\UxComponentRenderEventListener;

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
        private readonly UxComponentRenderEventListener $uxComponentRenderEventListener
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

        foreach($this->themeScripts->getThemeScripts() as $script) {
            if (!str_starts_with($script, 'js/components/')) {
                $scripts[] = $script;
            }
        }

        return $scripts;
    }

    public function componentScripts(): array
    {
        $scripts = [];

        foreach($this->themeScripts->getThemeScripts() as $script) {
            if (str_starts_with($script, 'js/components/')) {
                $scripts[] = $script;
            }
        }

        return $scripts;
    }

    public function mountedComponentScripts(): array
    {
        $scripts = [];
        $mountedScripts = [];
        $mountedComponents = $this->uxComponentRenderEventListener->getMountedComponents();

        foreach($mountedComponents as $component) {
            $mountedScripts[] = 'js/components/'.str_replace(':', '/', $component).'.js';
        }

        foreach($this->themeScripts->getThemeScripts() as $script) {
            if (str_starts_with($script, 'js/components/') && in_array($script, $mountedScripts)) {
                $scripts[] = $script;
            }
        }

        return $scripts;
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
