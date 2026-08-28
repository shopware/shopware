<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Framework\Util;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlPurifierConfigProvider;

/**
 * @internal
 */
#[Package('framework')]
class StaticHtmlPurifierConfigProvider extends HtmlPurifierConfigProvider
{
    public function __construct(private readonly \HTMLPurifier_Config $config)
    {
    }

    public function getConfig(): \HTMLPurifier_Config
    {
        return $this->config;
    }
}
