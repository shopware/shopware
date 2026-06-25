<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style;

use Shopware\Core\Framework\Log\Package;

/**
 * Per-element style state: a validated map of option name to its per-breakpoint scalar values
 * (`option => breakpoint => value`). A plain immutable DTO, not a Struct — it is emitted as a
 * raw array via ContentElement::jsonSerialize() and never needs an API alias.
 *
 * Immutability is load-bearing: the mutation subsystem aliases an untouched element's
 * ElementStyle by reference into rebuilt and cloned nodes, which is only safe because it cannot
 * be changed in place.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ElementStyle
{
    /**
     * @param array<string, array<string, string|int|float|bool>> $values
     */
    public function __construct(
        private array $values = [],
    ) {
    }

    /**
     * @return array<string, array<string, string|int|float|bool>>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }
}
