<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Validation wrapper for a set of binding specification declarations. Keyed by id (e.g.
 * `from-media-library`) so Symfony includes the id in violation property paths: bindings[from-media-library].type
 *
 * @internal
 */
#[Package('framework')]
final readonly class BindingSpecificationDtoCollection
{
    /**
     * @param array<string, BindingSpecificationDto> $bindings
     */
    public function __construct(
        #[Assert\Valid]
        public array $bindings,
    ) {
    }
}
