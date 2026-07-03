<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\Log\Package;

/**
 * Sibling of {@see MutationResponse} for the resolve-and-diagnose route.
 *
 * Output-only: serialized to JSON and discarded — never cached, stored in a DAL SerializedField, or passed to
 * StructNormalizer::denormalize(). The transforming jsonSerialize() (empty map cast to {}) is safe only on this
 * path; a future requirement that caches or reconstructs this object must revisit it.
 *
 * @final
 */
#[Package('framework')]
class DiagnoseResponse implements \JsonSerializable
{
    /**
     * @param array<string, list<array<string, mixed>>> $resolutions per-element resolutions
     * @param array<string, mixed> $diagnostics normalized diagnostics report
     * @param array<string, list<string>> $applicableBindings applicable binding specification ids, keyed by element id
     */
    private function __construct(
        public array $resolutions,
        public array $diagnostics,
        public array $applicableBindings,
    ) {
    }

    /**
     * @param array<string, list<PropertyResolution>> $resolutions
     * @param array<string, list<string>> $applicableBindings applicable binding specification ids, keyed by element id
     */
    public static function fromReport(array $resolutions, DiagnosticsReport $report, array $applicableBindings): self
    {
        $normalizer = new LayoutDiagnosticsResultNormalizer();

        return new self(
            $normalizer->normalizeResolutions($resolutions),
            $normalizer->normalizeReport($report),
            $applicableBindings,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'resolutions' => (object) $this->resolutions,
            'diagnostics' => $this->diagnostics,
            'applicableBindings' => (object) $this->applicableBindings,
        ];
    }
}
