<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Loader;

use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ResolvedBindingSpecificationDto
{
    public function __construct(
        public string $id,
        public string $source,
        public BindingSpecificationDto $dto,
    ) {
    }

    public function toSpecification(): BindingSpecification
    {
        return $this->dto->toBindingSpecification($this->id, $this->source);
    }
}
