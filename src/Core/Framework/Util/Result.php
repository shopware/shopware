<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;

/**
 * The outcome of an operation that either succeeds or fails with errors.
 *
 * The payload is whatever the caller wants to carry on failure — a list, a collection.
 *
 * @template-covariant T
 */
#[Package('framework')]
final readonly class Result
{
    /**
     * @var T|null
     */
    public mixed $errors;

    private function __construct(
        private bool $ok,
        mixed $errors = null,
    ) {
        $this->errors = $errors;
    }

    /**
     * @phpstan-assert-if-false !null $this->errors
     */
    public function isOk(): bool
    {
        return $this->ok;
    }

    /**
     * @return self<never>
     */
    public static function ok(): self
    {
        return new self(true);
    }

    /**
     * @template TErrors
     *
     * @param TErrors $errors
     *
     * @return self<TErrors>
     */
    public static function failed(mixed $errors): self
    {
        return new self(false, $errors);
    }
}
