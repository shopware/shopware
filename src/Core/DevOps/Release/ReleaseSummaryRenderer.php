<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Release;

use Shopware\Core\Framework\Log\Package;

/**
 * Turns a {@see VerificationResult} into the two report surfaces the CI job shows: the plain-text
 * console log and the Markdown job summary. Kept separate from the verifier so the formatting can be
 * unit-tested without running git.
 *
 * @internal
 */
#[Package('framework')]
class ReleaseSummaryRenderer
{
    /**
     * @param string $commitUrlBase base URL for commit links (e.g. "https://github.com/owner/repo/commit");
     *                              empty when running outside GitHub Actions, which falls back to short SHAs
     */
    public function __construct(
        private readonly string $commitUrlBase = '',
    ) {
    }

    public function consoleReport(VerificationResult $result, string $releaseInfoFile): string
    {
        $report = '';

        if ($result->warnings !== []) {
            $report .= 'WARN: ' . \count($result->warnings) . " of {$result->total} entries need manual verification:\n\n";
            foreach ($result->warnings as $warning) {
                $report .= "  ? {$warning['heading']} [" . $this->commitRef($warning['sha']) . "] {$warning['note']}\n";
            }
            $report .= "\n";
        }

        if ($result->missing !== []) {
            $report .= 'MISSING: ' . \count($result->missing) . " of {$result->total} entries documented on trunk are absent from this release branch:\n\n";
            foreach ($result->missing as $missing) {
                $report .= "  x {$missing['heading']} [" . $this->commitRef($missing['sha']) . "]\n";
            }
            $report .= "\n";
        }

        if ($result->hasMissing()) {
            return $report . "These features were documented in {$releaseInfoFile} on trunk but have not been merged into this release branch.\n";
        }

        $ok = $result->total - \count($result->warnings);

        return $report . "OK: {$ok} of {$result->total} entries confirmed present. " . \count($result->warnings) . " need manual verification (see above).\n";
    }

    public function markdownSummary(string $versionPrefix, string $branchRef, string $releaseInfoFile, VerificationResult $result): string
    {
        $lines = [];
        $lines[] = "## Release content verification — `{$versionPrefix}.*`";
        $lines[] = '';
        $lines[] = \sprintf('**%d** confirmed · **%d** warning(s) · **%d** missing (of %d)', $result->confirmed, \count($result->warnings), \count($result->missing), $result->total);
        $lines[] = '';
        $lines[] = "- branch: `{$branchRef}`";
        $lines[] = "- file: `{$releaseInfoFile}`";
        $lines[] = '';

        if ($result->missing !== []) {
            $lines[] = '### ❌ Missing from this release branch';
            $lines[] = '';
            $lines[] = '| Entry | Trunk commit |';
            $lines[] = '| --- | --- |';
            foreach ($result->missing as $missing) {
                $lines[] = \sprintf('| %s | %s |', $this->mdTitle($missing['heading']), $this->commitMd($missing['sha']));
            }
            $lines[] = '';
        }

        if ($result->warnings !== []) {
            $lines[] = '### ⚠️ Needs manual verification';
            $lines[] = '';
            $lines[] = '| Entry | Trunk commit | Note |';
            $lines[] = '| --- | --- | --- |';
            foreach ($result->warnings as $warning) {
                $lines[] = \sprintf('| %s | %s | %s |', $this->mdTitle($warning['heading']), $this->commitMd($warning['sha']), $warning['note']);
            }
            $lines[] = '';
        }

        if ($result->missing === [] && $result->warnings === []) {
            $lines[] = "✅ All {$result->total} documented entries are present and traceable.";
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * One-line verdict for the `release-content/verify` commit status (the GitHub status description
     * limit is 140 characters, so this stays a short summary).
     */
    public function commitStatusDescription(VerificationResult $result, string $versionPrefix, string $targetBranch): string
    {
        if ($result->total === 0) {
            return "no entries for {$versionPrefix}.* on trunk — nothing to verify";
        }

        if ($result->hasMissing()) {
            return \sprintf('%d of %d documented entries missing from %s', \count($result->missing), $result->total, $targetBranch);
        }

        return \sprintf('%d of %d entries confirmed, %d need manual verification', $result->confirmed, $result->total, \count($result->warnings));
    }

    /**
     * Commit reference for the console log: short SHA locally, "short (full-url)" inside GitHub Actions.
     */
    private function commitRef(string $sha): string
    {
        if ($sha === '') {
            return 'unknown';
        }

        $short = substr($sha, 0, 8);

        return $this->commitUrlBase !== '' ? \sprintf('%s (%s/%s)', $short, $this->commitUrlBase, $sha) : $short;
    }

    /**
     * Commit reference as a Markdown link for the job summary (code span when unknown).
     */
    private function commitMd(string $sha): string
    {
        if ($sha === '') {
            return '`unknown`';
        }

        $short = substr($sha, 0, 8);

        return $this->commitUrlBase !== '' ? \sprintf('[`%s`](%s/%s)', $short, $this->commitUrlBase, $sha) : \sprintf('`%s`', $short);
    }

    /**
     * Strip the leading "### " from a heading and escape pipes for Markdown table cells.
     */
    private function mdTitle(string $heading): string
    {
        return str_replace('|', '\\|', (string) preg_replace('/^###[[:space:]]+/', '', $heading));
    }
}
