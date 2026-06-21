<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Binding\SourceBinding;
use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * The layout gate: the two gate predicates plus the binding-enforcement seam. It never throws — it returns
 * a {@see DiagnosticsReport}. Well-formedness gates persistence; resolvability for a bound source gates serving.
 * {@see isBindingEnforced()} is the single overridable point through which a future versioning/draft system can
 * exempt a binding from the serving gate; the default enforces every binding.
 */
#[Package('framework')]
class LayoutGate
{
    /**
     * Write-context state that suppresses the content-layout gates. Migrations and trusted bulk importers set
     * it deliberately via Context::addState; absent the flag, validation runs on every write path incl. Sync API.
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
     * @param list<ContentElement> $tree
     */
    public function wellFormedness(array $tree, Context $context): DiagnosticsReport
    {
        return $this->diagnostics->analyze($tree, null, $context)->report;
    }

    /**
     * Full resolvability for a bound source's root context — the serving gate.
     *
     * @param list<ContentElement> $tree
     * @param list<ProvidedContext> $providedRootContext
     */
    public function resolvability(array $tree, array $providedRootContext, Context $context): DiagnosticsReport
    {
        return $this->diagnostics->analyze($tree, $providedRootContext, $context)->report;
    }

    /**
     * Whether a binding must pass the serving gate. Default: every binding is enforced. A future draft system
     * overrides this to exempt non-live versions while the published version still must pass.
     */
    public function isBindingEnforced(SourceBinding $binding): bool
    {
        return true;
    }
}
