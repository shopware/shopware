<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Deprecation\BCChange;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Signals that the exception types thrown by the method will change or be removed in the given version.
 *
 * Callers catching the currently documented exception types must prepare to catch the announced
 * types before the change happens. An empty `$newExceptions` list means callers must no longer
 * expect an exception. Tooling (e.g. Rector) can update catch blocks by reading the list.
 *
 * Only announce real contract changes: switching to exceptions that are already covered by the
 * current `@throws` contract (narrowing) does not affect callers and needs no announcement.
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_METHOD)]
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
