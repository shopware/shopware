<?php declare(strict_types=1);

namespace Shopware\Storefront\Storybook;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlPurifierConfigProvider;

/**
 * @internal
 */
#[Package('discovery')]
class StorybookHtmlPurifierConfigProvider extends HtmlPurifierConfigProvider
{
    public function __construct(private readonly string $requestOrigin)
    {
    }

    public function getConfig(): \HTMLPurifier_Config
    {
        $config = parent::getConfig();
        // WHY: Keep form controls visible in previews; the sanitizer's allowlist still excludes forms.
        $config->set('HTML.Forms', true);
        // WHY: Absolute URLs prevent resources from resolving against the Storybook document's origin.
        $config->set('URI.Base', $this->requestOrigin . '/');
        $config->set('URI.MakeAbsolute', true);

        return $config;
    }
}
