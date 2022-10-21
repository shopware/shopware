<?php declare(strict_types=1);

namespace Shopware\Core\Test\Annotation;

/**
 * @internal
 * @Annotation
 *
 * @Target({"METHOD", "CLASS"})
 */
final class DisabledFeatures
{
    /**
     * @var array<string>
     */
    public array $features;
}
