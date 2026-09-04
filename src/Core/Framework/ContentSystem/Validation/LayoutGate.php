<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Log\Package;

/**
 * The layout gate: the two gate predicates. It never throws — it returns a {@see DiagnosticsReport}.
 * Well-formedness gates persistence; resolvability for the declared root source gates serving.
 *
 * @internal
 */
#[Package('framework')]
class LayoutGate
{
    /**
     * Write-context state that suppresses the content-layout gates. Trusted bulk importers set it deliberately via
     * Context::addState; absent the flag, validation runs on every write path incl. Sync API. Migrations never
     * reach this gate — they write raw SQL through Connection, bypassing the DAL.
     */
    public const SKIP_VALIDATION_STATE = 'content-system-skip-layout-validation';

    /**
     * @internal
     */
    public function __construct(
        private readonly LayoutDiagnostics $diagnostics,
    ) {
    }

    /**
     * Structural validity and validity of present wiring only — the persistence gate.
     *
     * @param list<StoredElement> $tree
     */
    public function wellFormedness(array $tree): DiagnosticsReport
    {
        return $this->diagnostics->analyze($tree, null)->report;
    }

    /**
     * Full resolvability for a bound source's root context — the serving gate.
     *
     * @param list<StoredElement> $tree
     * @param list<ProvidedContext> $providedRootContext
     */
    public function resolvability(array $tree, array $providedRootContext): DiagnosticsReport
    {
        return $this->diagnostics->analyze($tree, $providedRootContext)->report;
    }
}
