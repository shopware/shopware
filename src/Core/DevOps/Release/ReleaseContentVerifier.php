<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Release;

use Shopware\Core\Framework\Log\Package;

/**
 * Verifies that every feature heading documented in trunk's RELEASE_INFO for a version prefix is
 * also present on the release branch, and that the commit which introduced it on trunk is reachable
 * from that branch.
 *
 * Per documented heading three checks are combined:
 *  1. text     — is the heading present in the branch's copy of the file?
 *  2. commit   — is the trunk commit that introduced the heading an ancestor of the branch?
 *  3. docs-only — did that commit touch only RELEASE_INFO (so reachability says nothing about the
 *                 feature code)?
 *
 * @internal
 */
#[Package('framework')]
class ReleaseContentVerifier
{
    public const NOTE_TEXT_WITHOUT_COMMIT = 'RELEASE_INFO present but trunk commit not in branch — verify cherry-pick includes feature code';
    public const NOTE_COMMIT_WITHOUT_TEXT = 'code commit present but RELEASE_INFO entry missing from branch';
    public const NOTE_DOCS_ONLY = 'RELEASE_INFO was updated in a docs-only commit — feature code commit is unknown, verify manually';

    public function __construct(
        private readonly GitReader $git,
    ) {
    }

    public function verify(string $versionPrefix, string $trunkRef, string $branchRef, string $releaseInfoFile): VerificationResult
    {
        // Trunk is the authoritative source for what is supposed to be in the release; the branch is
        // what actually shipped (or will ship).
        $trunkHeadings = self::extractHeadings($this->git->showFile($trunkRef, $releaseInfoFile), $versionPrefix);
        $branchHeadings = self::extractHeadings($this->git->showFile($branchRef, $releaseInfoFile), $versionPrefix);

        /** @var list<array{heading: string, sha: string}> $missing */
        $missing = [];
        /** @var list<array{heading: string, sha: string, note: string}> $warnings */
        $warnings = [];
        $confirmed = 0;

        foreach ($trunkHeadings as $heading) {
            $textPresent = \in_array($heading, $branchHeadings, true);

            $introducingCommit = $this->git->findIntroducingCommit($trunkRef, $heading, $releaseInfoFile);
            $commitReachable = false;
            $docsOnly = false;

            if ($introducingCommit !== '') {
                $commitReachable = $this->git->isAncestor($introducingCommit, $branchRef);

                $changedFiles = $this->git->changedFiles($introducingCommit);
                $docsOnly = \count($changedFiles) === 1 && $changedFiles[0] === $releaseInfoFile;
            }

            if ($textPresent && $commitReachable) {
                if ($docsOnly) {
                    // Heading and reachable commit, but the commit only touched RELEASE_INFO — reachability
                    // says nothing about the feature code, so flag it for manual review.
                    $warnings[] = ['heading' => $heading, 'sha' => $introducingCommit, 'note' => self::NOTE_DOCS_ONLY];
                } else {
                    ++$confirmed; // heading and feature code landed together and are reachable
                }
            } elseif (!$textPresent && !$commitReachable) {
                $missing[] = ['heading' => $heading, 'sha' => $introducingCommit];
            } elseif ($textPresent) {
                // heading present but the introducing commit is not an ancestor of the branch
                $warnings[] = ['heading' => $heading, 'sha' => $introducingCommit, 'note' => self::NOTE_TEXT_WITHOUT_COMMIT];
            } else {
                // introducing commit reachable but the heading text is missing from the branch
                $warnings[] = ['heading' => $heading, 'sha' => $introducingCommit, 'note' => self::NOTE_COMMIT_WITHOUT_TEXT];
            }
        }

        // Order entries by the introducing commit hash so log and summary are stable and grouped by commit.
        $byCommit = static fn (array $a, array $b): int => strcmp($a['sha'], $b['sha']);
        usort($missing, $byCommit);
        usort($warnings, $byCommit);

        return new VerificationResult(\count($trunkHeadings), $confirmed, $missing, $warnings);
    }

    /**
     * Collects every "### " heading that appears under a "# <version-prefix>.*" section.
     *
     * @return list<string>
     */
    public static function extractHeadings(string $content, string $versionPrefix): array
    {
        $escapedPrefix = preg_quote($versionPrefix, '/');
        $inSection = false;
        $headings = [];

        foreach (explode("\n", $content) as $line) {
            // "# 6.7.11.0" → enter the section for this version prefix.
            if (preg_match('/^#[[:space:]]+' . $escapedPrefix . '\./', $line) === 1) {
                $inSection = true;

                continue;
            }
            // Any other top-level "# " heading closes the section.
            if ($inSection && preg_match('/^#[[:space:]]/', $line) === 1) {
                $inSection = false;

                continue;
            }
            if ($inSection && preg_match('/^###[[:space:]]/', $line) === 1) {
                $headings[] = $line;
            }
        }

        return $headings;
    }
}
