<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * Adds a finite set of allowed values (a "choice list") to a DAL field.
 *
 * Primary use cases:
 * - OpenAPI schema generation: enrich the schema with an `enum` for better API docs and clients.
 * - Optional write validation: when `strict=true` and supported by the field serializer,
 *   writes are validated to only accept values from the given choice list.
 *
 * Note: A field with this flag is non-strict by default. To enforce on write, set `strict=true`.
 */
#[Package('framework')]
class Choice extends Flag
{
    /**
     * @param list<string|bool|int|float> $choices A list of allowed values for this field.
     *                                             Used for documentation, and optionally for strict write validation.
     * @param bool $strict When true, the write layer enforces that only values from `$choices` are accepted.
     *                     When false (default), the choices are documentation-only.
     */
    public function __construct(
        private readonly array $choices,
        private readonly bool $strict = false,
    ) {
    }

    /**
     * @return list<string|bool|int|float> The configured list of allowed values.
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

    /**
     * Indicates whether writes should be strictly validated against the choice list.
     */
    public function isStrict(): bool
    {
        return $this->strict;
    }

    public function parse(): \Generator
    {
        yield 'choice' => [
            'choices' => $this->choices,
            'strict' => $this->strict,
        ];
    }
}
