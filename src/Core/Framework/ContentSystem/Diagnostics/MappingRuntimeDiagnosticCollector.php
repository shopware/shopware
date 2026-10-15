<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Diagnostics;

use Shopware\Core\Framework\Log\Package;

/**
 * Collects mapping findings for one render. The rendered result carries these separately from its tree, so
 * Storefront and preview output stay identical while a diagnostics caller can report runtime outcomes.
 *
 * @internal
 */
#[Package('framework')]
final class MappingRuntimeDiagnosticCollector
{
    /**
     * @var array<string, Violation>
     */
    private array $violations = [];

    public function record(string $elementId, string $propertyKey, string $source, string $message): void
    {
        $violation = new Violation(
            ViolationCode::InvalidMapping,
            $elementId,
            $propertyKey,
            $message,
        );

        $this->violations[implode("\0", [$elementId, $propertyKey, $source, $message])] = $violation;
    }

    /**
     * @return list<Violation>
     */
    public function violations(): array
    {
        return array_values($this->violations);
    }
}
