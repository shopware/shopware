<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Loader;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\Dto\ElementTypeSpecificationDto;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ResolvedElementTypeSpecificationDto
{
    public function __construct(
        public string $name,
        public string $source,
        public ElementTypeSpecificationDto $dto,
    ) {
    }

    public function toSpecification(): ContentSystemElementTypeSpecification
    {
        return $this->dto->toContentSystemElementTypeSpecification($this->name, $this->source);
    }
}
