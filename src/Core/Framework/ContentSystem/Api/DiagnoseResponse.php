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
     */
    private function __construct(
        public array $resolutions,
        public array $diagnostics,
    ) {
    }

    /**
     * @param array<string, list<PropertyResolution>> $resolutions
     */
    public static function fromReport(array $resolutions, DiagnosticsReport $report): self
    {
        $normalizer = new LayoutDiagnosticsResultNormalizer();

        return new self(
            $normalizer->normalizeResolutions($resolutions),
            $normalizer->normalizeReport($report),
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
        ];
    }
}
