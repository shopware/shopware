<?php declare(strict_types=1);

namespace Shopware\Core\Test\Annotation;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @Annotation
 *
 * @Target({"METHOD", "CLASS"})
 */
#[Package('core')]
final class DisabledFeatures
{
    /**
     * @var array<string>
     */
    public array $features;
}
