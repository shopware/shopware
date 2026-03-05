<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class Choice extends Flag
{
    /**
     * @param list<string|bool|int|float> $choices A list of allowed values for this field. Used for documentation, and optionally for strict write validation.
     * @param bool $strict When true, the write layer enforces that only values from `$choices` are accepted. When false (default), the choices are documentation-only.
     */
    public function __construct(
        private readonly array $choices,
        private readonly bool $strict = false,
    ) {
    }

    /**
     * @return list<string|bool|int|float>
     */
    public function getChoices(): array
    {
        return $this->choices;
    }

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
