<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;

/**
 * @Decoratable
 */
interface BundleConfigGeneratorInterface
{
    /**
     * Returns the bundle config for the webpack plugin injector
     */
    public function getConfig(): array;
}
