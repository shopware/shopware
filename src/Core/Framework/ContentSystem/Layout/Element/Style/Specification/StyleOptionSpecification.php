<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification;

use Shopware\Core\Framework\Log\Package;

/**
 * Declared contract of one universal style option: its name (the Store-API wire key, e.g.
 * `col-span`), an optional kind discriminator, an adminUI passthrough block, and the value
 * vocabulary/bounds on the StyleOptionValueType. toSchema() serializes it for introspection.
 *
 * @phpstan-type StyleOptionSchema = array{
 *     type: string,
 *     enum: list<string|int|float|bool>|null,
 *     range: array{min?: int|float, max?: int|float}|null,
 *     maxLength: int|null,
 *     default: string|int|float|bool|null,
 *     breakpointAware: bool,
 *     adminUI: array<string, mixed>|null
 * }
 */
#[Package('framework')]
final readonly class StyleOptionSpecification
{
    /**
     * The one declared kind: a box-spacing option, whose value is canonicalised into explicit
     * four-part CSS by ElementStyleNormalizer.
     */
    public const KIND_BOX_SPACING = 'box-spacing';

    /**
     * @param array<string, mixed>|null $adminUI
     */
    public function __construct(
        private string $name,
        private StyleOptionValueType $valueType,
        private bool $breakpointAware,
        private ?array $adminUI,
        private string $source = '',
        private ?string $kind = null,
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

    public function breakpointAware(): bool
    {
        return $this->breakpointAware;
    }

    public function source(): string
    {
        return $this->source;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function adminUI(): ?array
    {
        return $this->adminUI;
    }

    public function kind(): ?string
    {
        return $this->kind;
    }

    /**
     * @return StyleOptionSchema
     */
    public function toSchema(): array
    {
        return [
            ...$this->valueType->toSchema(),
            'breakpointAware' => $this->breakpointAware,
            'adminUI' => $this->adminUI,
        ];
    }
}
