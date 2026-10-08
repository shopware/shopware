<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentPropertyProjection;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class ContextConsumer implements \JsonSerializable
{
    /**
     * `$source` identifies the typed data source for a mapping. The consumer map key remains the destination
     * property, so several properties may use the same source reference.
     *
     * `$projection` names a registered {@see AbstractContentPropertyProjection} applied to the mapped value.
     * It is valid only when `$source` is present.
     */
    public function __construct(
        public ContextType $type,
        public bool $required,
        public bool $redistribute = false,
        public ?string $consumerAlias = null,
        public ?string $propertyAlias = null,
        public ConsumerScope $scope = ConsumerScope::Parent,
        public ?string $projection = null,
        public ?MappingSourceReference $source = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'type' => $this->type->value,
            'required' => $this->required,
        ];

        if ($this->redistribute) {
            $data['redistribute'] = true;
        }

        if ($this->consumerAlias !== null) {
            $data['consumerAlias'] = $this->consumerAlias;
        }

        if ($this->propertyAlias !== null) {
            $data['propertyAlias'] = $this->propertyAlias;
        }

        if ($this->scope !== ConsumerScope::Parent) {
            $data['scope'] = $this->scope->value;
        }

        if ($this->projection !== null) {
            $data['projection'] = $this->projection;
        }

        if ($this->source !== null) {
            $data['source'] = $this->source->jsonSerialize();
        }

        return $data;
    }
}
