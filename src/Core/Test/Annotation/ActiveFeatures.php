<?php declare(strict_types=1);

namespace Shopware\Core\Test\Annotation;

/**
 * @internal
 * @Annotation
 *
 * @Target({"METHOD"})
 */
final class ActiveFeatures
{
    /**
     * @var array<string>
     */
    public array $features;
}
