<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * One `resolves` entry of a {@see BindingSpecification}: the loader source and its config for a single
 * reference property key. Becomes a `DataRequirement` downstream.
 *
 * @internal
 */
#[Package('framework')]
final readonly class LoaderBinding
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public string $source,
        public array $config,
    ) {
    }
}
