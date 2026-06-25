<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto\StyleOptionSpecificationDto;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * Bridges loading and specification creation: a deserialized style option DTO together with the
 * name (from the file or DB row) and source label it was loaded under.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ResolvedStyleOptionSpecificationDto
{
    public function __construct(
        public string $name,
        public string $source,
        public StyleOptionSpecificationDto $dto,
    ) {
    }

    public function toSpecification(): StyleOptionSpecification
    {
        return $this->dto->toStyleOptionSpecification($this->name, $this->source);
    }
}
