<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Resolution;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * A single context value available at an element's position: a provider's key/FQCN plus how it is distributed.
 *
 * `$root` marks the entry as root-ambient: supplied by the layout's bound root source rather than by an
 * element. The root-source registry sets the flag on every entry it resolves.
 */
#[Package('framework')]
final readonly class ProvidedContext
{
    public function __construct(
        public string $contextKey,
        public string $fqcn,
        public ContextType $contextType,
        public ?string $providerElementId,
        public DistributionStrategy $distribution,
        public ?string $path = null,
        public bool $root = false,
    ) {
    }
}
