<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Loader;

use Shopware\Core\Framework\Log\Package;

/**
 * Deliberately distinct from {@see \Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeSourceDirectory}:
 * the two are structurally identical, but each belongs to its own loader and the two systems evolve
 * independently, so they are not consolidated.
 *
 * @internal
 */
#[Package('framework')]
final readonly class BindingSpecificationSourceDirectory
{
    public function __construct(
        public string $source,
        public string $path,
        public string $prefix,
    ) {
    }
}
