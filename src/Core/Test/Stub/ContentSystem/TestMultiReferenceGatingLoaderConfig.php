<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * The config object of {@see TestMultiReferenceGatingLoader}: an entity name, two required property references, and
 * one optional defaulted property reference. Its `jsonSerialize()` is the encode form the `UnfilledRequiredInput`
 * rule reads.
 */
#[Package('framework')]
final readonly class TestMultiReferenceGatingLoaderConfig extends AbstractContentDataLoaderConfig
{
    public function __construct(
        public string $entity,
        public string $property,
        public string $secondProperty,
        public ?string $activeProperty = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'entity' => $this->entity,
            'property' => $this->property,
            'secondProperty' => $this->secondProperty,
        ];

        if ($this->activeProperty !== null) {
            $data['activeProperty'] = $this->activeProperty;
        }

        return $data;
    }
}
