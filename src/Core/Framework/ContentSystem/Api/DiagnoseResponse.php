<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Shopware\Core\Framework\ContentSystem\Resolution\PropertyResolution;
use Shopware\Core\Framework\Log\Package;

/**
 * The wire response for the resolve-and-diagnose route, and the single definition of its shape and resolutions
 * map encoding. The sibling of {@see MutationResponse}.
 *
 * Output-only: this object is serialized to JSON for the HTTP response and discarded. It is never cached, never
 * stored in a DAL SerializedField, never sent over the message bus, and never passed to StructNormalizer::denormalize().
 * The transforming jsonSerialize() (empty map cast to {}, no extensions/apiAlias) is safe only on that path; a future
 * requirement that caches or reconstructs this object must revisit it.
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
