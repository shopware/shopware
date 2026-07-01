<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Loader;

use Shopware\Core\Framework\ContentSystem\Binding\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\Log\Package;

/**
 * Bridges loading and specification creation: a deserialized binding specification DTO together with
 * the id (from the YAML body) and source label it was loaded under.
 *
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
