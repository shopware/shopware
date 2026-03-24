<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Type\Registry;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class CompiledElementTypeDefinition
{
    public function __construct(
        public ContentSystemElementTypeSpecification $specification,
        public string $source,
    ) {
    }

    public function name(): string
    {
        return $this->specification->name();
    }
}
