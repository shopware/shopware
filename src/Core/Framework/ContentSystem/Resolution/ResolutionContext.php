<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Resolution;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ResolutionContext
{
    /**
     * @param list<ProvidedContext> $available context available AT this element's position
     */
    public function __construct(
        public string $elementId,
        public array $available,
    ) {
    }
}
