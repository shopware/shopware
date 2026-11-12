<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ElementTypeSpecificationDtoCollection
{
    /**
     * Keyed by element type name (e.g. "Sw:Product:Card") so Symfony includes
     * the name in violation property paths: types[Sw:Product:Card].label
     *
     * @param array<string, ElementTypeSpecificationDto> $types
     */
    public function __construct(
        #[Assert\Valid]
        public array $types,
    ) {
    }
}
