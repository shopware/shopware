<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\Log\Package;

/**
 * Provider outcome: resolved values (including null), missing source data, or a provider failure.
 *
 * @internal
 */
#[Package('framework')]
final readonly class MappingSourceResolution
{
    private function __construct(
        public bool $hasValue,
        public mixed $value,
        public ?\Throwable $failure,
    ) {
    }

    public static function resolved(mixed $value): self
    {
        return new self(true, $value, null);
    }

    public static function noValue(): self
    {
        return new self(false, null, null);
    }

    public static function failed(\Throwable $failure): self
    {
        return new self(false, null, $failure);
    }

    public function isFailure(): bool
    {
        return $this->failure !== null;
    }

    public function isNoValue(): bool
    {
        return !$this->hasValue && $this->failure === null;
    }
}
