<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\Release;

use Shopware\Core\DevOps\Release\GitReader;
use Shopware\Core\Framework\Log\Package;

/**
 * In-memory {@see GitReader} for the release-content verifier tests: every answer is configured up
 * front, so scenarios read as data instead of subprocess plumbing.
 *
 * @internal
 */
#[Package('framework')]
class FakeGitReader implements GitReader
{
    /**
     * @param array<string, string> $files "<ref>:<path>" => file content
     * @param array<string, string> $introducing heading => introducing commit sha on trunk
     * @param list<string> $existingRefs refs that resolve to a commit
     * @param list<string> $ancestors commits reachable from the release branch
     * @param array<string, list<string>> $changedFiles sha => files that commit changed
     */
    public function __construct(
        private readonly array $files = [],
        private readonly array $introducing = [],
        private readonly array $existingRefs = [],
        private readonly array $ancestors = [],
        private readonly array $changedFiles = [],
    ) {
    }

    public function showFile(string $ref, string $path): string
    {
        return $this->files[$ref . ':' . $path] ?? '';
    }

    public function refExists(string $ref): bool
    {
        return \in_array($ref, $this->existingRefs, true);
    }

    public function findIntroducingCommit(string $ref, string $needle, string $path): string
    {
        return $this->introducing[$needle] ?? '';
    }

    public function isAncestor(string $commit, string $ref): bool
    {
        return \in_array($commit, $this->ancestors, true);
    }

    public function changedFiles(string $commit): array
    {
        return $this->changedFiles[$commit] ?? [];
    }
}
