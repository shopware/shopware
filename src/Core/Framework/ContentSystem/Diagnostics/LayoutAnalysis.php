<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Diagnostics;

use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\Log\Package;

/**
 * The output of {@see LayoutDiagnostics::analyze()}: the per-element resolutions map plus the diagnostics report.
 *
 * @internal
 */
#[Package('framework')]
final readonly class LayoutAnalysis
{
    /**
     * @param array<string, list<PropertyResolution>> $resolutions keyed by element id
     */
    public function __construct(
        public DiagnosticsReport $report,
        public array $resolutions,
    ) {
    }
}
