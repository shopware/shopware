<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * Declared contract of one universal style option. The name is the Store-API wire key
 * (e.g. `col-span`); the value vocabulary and bounds live on the StyleOptionValueType.
 * toSchema() feeds both the dedicated style-option introspection endpoint and the folded
 * styleOptions section of the element-types endpoint, and is the single source the validation
 * constraints are derived from, so the two never drift.
 *
 * @internal
 *
 * @phpstan-type StyleOptionSchema = array{
 *     type: string,
 *     enum: list<string|int|float|bool>|null,
 *     range: array{min?: int|float, max?: int|float}|null,
 *     maxLength: int|null,
 *     default: string|int|float|bool|null,
 *     adminUI: array<string, mixed>|null
 * }
 */
#[Package('framework')]
final readonly class StyleOptionSpecification
{
    /**
     * @param array<string, mixed>|null $adminUI
     */
    public function __construct(
        private string $name,
        private StyleOptionValueType $valueType,
        private ?array $adminUI,
        private string $source = '',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function valueType(): StyleOptionValueType
    {
        return $this->valueType;
    }

    public function source(): string
    {
        return $this->source;
    }

    /**
     * @return StyleOptionSchema
     */
    public function toSchema(): array
    {
        return [
            ...$this->valueType->toSchema(),
            'adminUI' => $this->adminUI,
        ];
    }
}
