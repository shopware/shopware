<?php declare(strict_types=1);

namespace Shopware\Core\Test\Annotation;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final class DisabledFeatures
{
    /**
     * @param array<string> $features
     */
    public function __construct(public array $features = [])
    {
    }
}
