<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
interface BundleConfigGeneratorInterface
{
    /**
     * Returns the bundle config for the webpack plugin injector
     */
    public function getConfig(): array;
}
