<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Twig;

use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;

class TemplateConfigAccessor
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var ThemeConfigValueAccessor
     */
    private $themeConfigAccessor;

    /**
     * @internal
     */
    public function __construct(SystemConfigService $config, ThemeConfigValueAccessor $themeConfigAccessor)
    {
        $this->systemConfigService = $config;
        $this->themeConfigAccessor = $themeConfigAccessor;
    }

    /**
     * @return string|bool|array|float|int|null
     */
    public function config(string $key, ?string $salesChannelId)
    {
        $static = $this->getStatic();

        if (\array_key_exists($key, $static)) {
            Feature::triggerDeprecationOrThrow('v6.5.0.0', "The config variable {$key} has been deprecated and will be removed in v6.5.0.0");

            return $static[$key];
        }

        return $this->systemConfigService->get($key, $salesChannelId);
    }

    /**
     * @return string|bool|array|float|int|null
     */
    public function theme(string $key, SalesChannelContext $context, ?string $themeId)
    {
        return $this->themeConfigAccessor->get($key, $context, $themeId);
    }

    /**
     * @deprecated tag:v6.5.0 - All of these configs will be removed and were never configurable by the user
     */
    private function getStatic(): array
    {
        return [
            'seo.descriptionMaxLength' => 150,
            'cms.revocationNoticeCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
            'cms.taxCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
            'cms.tosCmsPageId' => '00B9A8636F954277AE424E6C1C36A1F5',
            'confirm.revocationNotice' => true,
        ];
    }
}
