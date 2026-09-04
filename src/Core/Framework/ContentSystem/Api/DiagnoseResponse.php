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
 * @internal
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
