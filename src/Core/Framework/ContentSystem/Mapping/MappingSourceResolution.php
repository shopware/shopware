<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\Log\Package;

/**
 * Provider outcome: a resolved value (including null) or missing source data. Provider errors propagate.
 */
#[Package('framework')]
final readonly class MappingSourceResolution
{
    private function __construct(
        public bool $hasValue,
        public mixed $value,
    ) {
    }

    public static function resolved(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function noValue(): self
    {
        return new self(false, null);
    }

    public function isNoValue(): bool
    {
        return !$this->hasValue;
    }
}
