<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Release;

use Shopware\Core\Framework\Log\Package;

/**
 * Outcome of a {@see ReleaseContentVerifier} run: how many documented headings were confirmed,
 * which are missing from the release branch, and which need manual verification. The verifier
 * returns this instead of writing output or setting an exit code, so callers decide how to report.
 *
 * @internal
 *
 * @phpstan-type MissingEntry array{heading: string, sha: string}
 * @phpstan-type WarningEntry array{heading: string, sha: string, note: string}
 */
#[Package('framework')]
class VerificationResult
{
    /**
     * @param list<MissingEntry> $missing
     * @param list<WarningEntry> $warnings
     */
    public function __construct(
        public readonly int $total,
        public readonly int $confirmed,
        public readonly array $missing,
        public readonly array $warnings,
    ) {
    }

    public function hasMissing(): bool
    {
        return $this->missing !== [];
    }
}
