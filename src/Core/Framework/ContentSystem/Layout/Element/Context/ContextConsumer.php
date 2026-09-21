<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Mapping\Projection\AbstractContentPropertyProjection;
use Shopware\Core\Framework\ContentSystem\Rendering\ContextDeliveryResolver;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class ContextConsumer implements \JsonSerializable
{
    /**
     * `$projection` names a registered {@see AbstractContentPropertyProjection} applied to the resolved value
     * before it lands on the property, and it is a DATA-MAPPING field despite living on the general consumer
     * shape: a stored mapping is a consumer, so this is where one can be recorded. Both the decoder and the
     * write-path constraints refuse it on any consumer that is not a root-scoped dotted one, because
     * {@see ContextDeliveryResolver::ambientValueFor()} applies it only there and a projection anywhere else
     * would quietly do nothing.
     */
    public function __construct(
        public ContextType $type,
        public bool $required,
        public bool $redistribute = false,
        public ?string $consumerAlias = null,
        public ?string $propertyAlias = null,
        public ConsumerScope $scope = ConsumerScope::Parent,
        public ?string $projection = null,
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

        return $data;
    }
}
