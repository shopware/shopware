<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * One `resolves` entry of a {@see BindingSpecification}. Becomes a `DataRequirement` downstream.
 */
#[Package('framework')]
final readonly class LoaderBinding
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public string $loader,
        public array $config,
    ) {
    }
}
