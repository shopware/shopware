<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Diagnostics;

use Shopware\Core\Framework\Log\Package;

/**
 * The layout-wide diagnostics result. Two predicates ground the two gates:
 * {@see isWellFormed()} gates persistence (no intrinsic-scope errors),
 * {@see isResolvable()} gates serving (no binding-scope errors, run with a bound source).
 *
 * @internal
 */
#[Package('framework')]
final readonly class DiagnosticsReport
{
    /**
     * @param list<Violation> $violations
     */
    public function __construct(public array $violations)
    {
    }

    /**
     * @return list<Violation>
     */
    public function intrinsicErrors(): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn (Violation $violation): bool => $violation->severity() === ViolationSeverity::Error
                && $violation->scope() === ViolationScope::Intrinsic
        ));
    }

    /**
     * @return list<Violation>
     */
    public function bindingErrors(): array
    {
        return array_values(array_filter(
            $this->violations,
            static fn (Violation $violation): bool => $violation->severity() === ViolationSeverity::Error
                && $violation->scope() === ViolationScope::Binding
        ));
    }

    public function isWellFormed(): bool
    {
        return $this->intrinsicErrors() === [];
    }

    public function isResolvable(): bool
    {
        return $this->bindingErrors() === [];
    }
}
