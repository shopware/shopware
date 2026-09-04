<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Resolution;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * A possible source for filling a reference property: either an ancestor/root provider or a data loader.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ResolutionCandidate
{
    /**
     * @param array<string, mixed>|null $configTemplate
     */
    public function __construct(
        public CandidateOrigin $origin,
        public ?string $contextKey = null,
        public ?string $providerElementId = null,
        public ?string $path = null,
        public ?DistributionStrategy $distribution = null,
        public ?ContextType $contextType = null,
        public ?string $loaderSource = null,
        public ?array $configTemplate = null,
        public bool $configComplete = false,
    ) {
    }
}
