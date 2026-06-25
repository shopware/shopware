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
 * is supplied to toStyleOptionSpecification(), mirroring the element-type DTO.
 *
 * @internal
 */
#[Package('framework')]
#[TypedStyleOption]
final readonly class StyleOptionSpecificationDto
{
    /**
     * $range is carried untyped (the raw YAML/DB map) so the validator can reject non-numeric
     * bounds at runtime; it is narrowed to the clean shape by buildRange() once validated.
     *
     * @param list<string|int|float|bool>|null $enum
     * @param array<string, mixed>|null $range
     * @param array<string, mixed>|null $adminUI
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: StyleOptionValueType::PRIMITIVE_TYPES)]
        public string $type,
        public ?array $enum,
        public ?array $range,
        #[Assert\Positive]
        public ?int $maxLength,
        public string|int|float|bool|null $default,
        public ?array $adminUI,
    ) {
    }

    public function toStyleOptionSpecification(string $name, string $source): StyleOptionSpecification
    {
        return new StyleOptionSpecification(
            $name,
            new StyleOptionValueType(
                $this->type,
                $this->enum,
                $this->buildRange(),
                $this->maxLength,
                $this->default,
            ),
            $this->adminUI,
            $source,
        );
    }

    /**
     * @return array{min?: int|float, max?: int|float}|null
     */
    private function buildRange(): ?array
    {
        if ($this->range === null) {
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
}
