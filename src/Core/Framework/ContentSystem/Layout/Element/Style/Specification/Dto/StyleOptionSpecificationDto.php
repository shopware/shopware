<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\Dto;

use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Validation\TypedStyleOption;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Deserialization + load-time validation shape for one style option declaration. The option name
 * is not carried here — it comes from the source (the YAML filename or the persisted DB row) and
 * is supplied to toStyleOptionSpecification().
 *
 * @internal
 */
#[Package('framework')]
#[TypedStyleOption]
final readonly class StyleOptionSpecificationDto
{
    /**
     * Every facet except type is carried raw (the raw YAML/DB value, typed mixed) so the validator can
     * reject a wrong-typed declaration at runtime rather than have it silently coerced before validation
     * sees it. The facets are narrowed to their clean shapes by buildEnum()/buildRange()/buildMaxLength()/
     * buildDefault()/buildAdminUI()/buildKind(), which run only after validation has passed.
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: StyleOptionValueType::PRIMITIVE_TYPES)]
        public string $type,
        public mixed $enum,
        public mixed $range,
        public mixed $maxLength,
        public mixed $default,
        public mixed $breakpointAware,
        public mixed $adminUI,
        #[Assert\Choice(choices: [StyleOptionSpecification::KIND_BOX_SPACING, null])]
        public mixed $kind = null,
    ) {
    }

    public function toStyleOptionSpecification(string $name, string $source): StyleOptionSpecification
    {
        return new StyleOptionSpecification(
            $name,
            new StyleOptionValueType(
                $this->type,
                $this->buildEnum(),
                $this->buildRange(),
                $this->buildMaxLength(),
                $this->buildDefault(),
            ),
            $this->buildBreakpointAware(),
            $this->buildAdminUI(),
            $source,
            $this->buildKind(),
        );
    }

    /**
     * @return list<string|int|float|bool>|null
     */
    private function buildEnum(): ?array
    {
        if (!\is_array($this->enum)) {
            return null;
        }

        // validateEnum has already rejected any non-scalar entry, so the values are scalars of the type.
        return array_values($this->enum);
    }

    /**
     * @return array{min?: int|float, max?: int|float}|null
     */
    private function buildRange(): ?array
    {
        if (!\is_array($this->range)) {
            return null;
        }

        $range = [];

        $min = $this->range['min'] ?? null;
        if (\is_int($min) || \is_float($min)) {
            $range['min'] = $min;
        }

        $max = $this->range['max'] ?? null;
        if (\is_int($max) || \is_float($max)) {
            $range['max'] = $max;
        }

        return $range === [] ? null : $range;
    }

    private function buildMaxLength(): ?int
    {
        return \is_int($this->maxLength) ? $this->maxLength : null;
    }

    private function buildDefault(): string|int|float|bool|null
    {
        // validateDefault has already rejected a non-scalar default; this only narrows for the value type.
        return \is_scalar($this->default) ? $this->default : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildAdminUI(): ?array
    {
        // An empty adminUI map collapses to null so toSchema() emits null, matching the OpenAPI contract.
        return \is_array($this->adminUI) && $this->adminUI !== [] ? $this->adminUI : null;
    }

    private function buildKind(): ?string
    {
        // Assert\Choice has already rejected any value other than a declared kind or null.
        return \is_string($this->kind) ? $this->kind : null;
    }

    private function buildBreakpointAware(): bool
    {
        // Absent (null) defaults to true: breakpoint-aware is the default; an option opts out with false.
        // A present non-bool is already rejected by TypedStyleOption, so it never reaches here valid.
        return \is_bool($this->breakpointAware) ? $this->breakpointAware : true;
    }
}
