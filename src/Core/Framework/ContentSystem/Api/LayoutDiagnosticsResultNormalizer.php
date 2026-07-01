<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Diagnostics\Violation;
use Shopware\Core\Framework\ContentSystem\Resolution\CandidateOrigin;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\ContentSystem\Resolution\ResolutionCandidate;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class LayoutDiagnosticsResultNormalizer
{
    /**
     * @param array<string, list<PropertyResolution>> $resolutions
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function normalizeResolutions(array $resolutions): array
    {
        return array_map(
            fn (array $resolutions): array => array_map($this->normalizeResolution(...), $resolutions),
            $resolutions,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeReport(DiagnosticsReport $report): array
    {
        return [
            'wellFormed' => $report->isWellFormed(),
            'resolvable' => $report->isResolvable(),
            'violations' => array_map($this->normalizeViolation(...), $report->violations),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResolution(PropertyResolution $resolution): array
    {
        return [
            'key' => $resolution->key,
            'kind' => $resolution->kind->value,
            'required' => $resolution->required,
            'type' => $resolution->type,
            'default' => $resolution->default,
            'fqcn' => $resolution->fqcn,
            'resolved' => $resolution->resolved === null ? null : $this->normalizeCandidate($resolution->resolved),
            'candidates' => array_map($this->normalizeCandidate(...), $resolution->candidates),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeCandidate(ResolutionCandidate $candidate): array
    {
        // configComplete is meaningful only for a Loader candidate. A Stored candidate carries no loader-shaped
        // fields — it is applied wiring, not an environment offer — so it serializes null. A Parent candidate is
        // pinned false per the documented wire contract, so a Parent constructed with configComplete=true can
        // never contradict the schema.
        $configComplete = match ($candidate->origin) {
            CandidateOrigin::Stored => null,
            CandidateOrigin::Parent => false,
            CandidateOrigin::Loader => $candidate->configComplete,
        };

        return [
            'origin' => $candidate->origin->value,
            'contextKey' => $candidate->contextKey,
            'providerElementId' => $candidate->providerElementId,
            'path' => $candidate->path,
            'distribution' => $candidate->distribution?->value,
            'contextType' => $candidate->contextType?->value,
            'loaderSource' => $candidate->loaderSource,
            'configTemplate' => $candidate->configTemplate,
            'configComplete' => $configComplete,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeViolation(Violation $violation): array
    {
        return [
            'code' => $violation->code->value,
            'scope' => $violation->scope()->value,
            'severity' => $violation->severity()->value,
            'elementId' => $violation->elementId,
            'key' => $violation->key,
            'message' => $violation->message,
            'candidates' => array_map($this->normalizeCandidate(...), $violation->candidates),
        ];
    }
}
