<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Release;

use Shopware\Core\Framework\Log\Package;

/**
 * Read-only git access used by the release-content verification. Abstracting it behind an
 * interface keeps {@see ReleaseContentVerifier} free of subprocess calls, so its logic can be
 * unit-tested against an in-memory fake instead of a real repository.
 *
 * @internal
 */
#[Package('framework')]
interface GitReader
{
    /**
     * Content of $path at $ref (e.g. "origin/trunk"), or an empty string when the ref or file
     * does not exist.
     */
    public function showFile(string $ref, string $path): string;

    /**
     * True when $ref resolves to a commit (i.e. it has been fetched).
     */
    public function refExists(string $ref): bool;

    /**
     * Most recent commit on $ref that changed the number of occurrences of $needle in $path
     * (pickaxe search), or an empty string when none is found.
     */
    public function findIntroducingCommit(string $ref, string $needle, string $path): string;

    /**
     * True when $commit is a direct ancestor of $ref.
     */
    public function isAncestor(string $commit, string $ref): bool;

    /**
     * Paths changed by $commit.
     *
     * @return list<string>
     */
    public function changedFiles(string $commit): array;
}
