<?php
declare(strict_types=1);

namespace Shopware\Storefront\Theme\ConfigLoader;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

#[Package('storefront')]
abstract class AbstractAvailableThemeProvider
{
    abstract public function getDecorated(): AbstractAvailableThemeProvider;

    /**
     * @deprecated tag:v6.6.0 - parameter $activeOnly will be introduced in future version
     *
     * @return array<string, string>
     */
    abstract public function load(Context $context/*, bool $activeOnly */): array;
}
