<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Style;

use Shopware\Core\Framework\Log\Package;

/**
 * Per-element style state: a validated `option => breakpoint => scalar` map. An immutable DTO
 * (not a Struct), emitted as a raw array by `StoredElement::jsonSerialize()` on the storage side and by the
 * full, decomposed and skeleton output encoders on the render side, all through `toArray()`.
 *
 * Immutable by contract: the mutation subsystem aliases instances by reference, so changing one
 * in place would be unsafe.
 */
#[Package('framework')]
final readonly class ElementStyle
{
    /**
     * @param array<string, string|int|float|bool|array<string, string|int|float|bool>> $values
     */
    public function __construct(
        private array $values = [],
    ) {
    }

    /**
     * @return array<string, string|int|float|bool|array<string, string|int|float|bool>>
     */
    public function toArray(): array
    {
        return $this->getValues();
    }

    /**
     * @return array<string, string|int|float|bool|array<string, string|int|float|bool>>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }
}
