<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class HtmlPurifierConfigProvider
{
    public function getConfig(): \HTMLPurifier_Config
    {
        return \HTMLPurifier_Config::createDefault();
    }
}
