<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * Signals that the exception types thrown by the method will change in the given version.
 *
 * Call sites catching the currently thrown exception types must adjust their catch blocks to the
 * announced types before the change happens; call sites that do not catch are not affected.
 * Tooling (e.g. Rector) can update catch blocks by reading `$newExceptions`.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
#[Package('framework')]
final class ExceptionChange implements CallSiteCompatibilityChange
{
    /**
     * @param list<class-string<\Throwable>> $newExceptions
     */
    public function __construct(
        public readonly string $version,
        public readonly array $newExceptions,
        public readonly ?string $description = null,
    ) {
    }
}
