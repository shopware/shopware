<?php

declare(strict_types=1);

// Verifies that every feature heading documented in trunk's RELEASE_INFO for the given
// version prefix is also present on the release branch, and that the commit which
// introduced it on trunk is reachable from that branch.
//
// The release branch defaults to "<version-prefix>.x" and is always compared via its remote
// ref (origin/<branch>), so the result does not depend on which branch is currently checked out.
//
// Usage: php verify-release-content.php <version-prefix> [release-branch]
// Example: php verify-release-content.php 6.7.11
// Example: php verify-release-content.php 6.7.11 6.7.11.x
//
// Exit codes:
//   0 — all entries verified (warnings may still be printed)
//   1 — one or more entries are missing from the release branch

const TRUNK_REF = 'origin/trunk';

$versionPrefix = $_SERVER['argv'][1] ?? '';
if ($versionPrefix === '') {
    fwrite(\STDERR, "Usage: {$_SERVER['argv'][0]} <version-prefix>\n");
    fwrite(\STDERR, "Example: {$_SERVER['argv'][0]} 6.7.11\n");
    exit(1);
}

$majorMinor = implode('.', \array_slice(explode('.', $versionPrefix), 0, 2));
$releaseInfoFile = "RELEASE_INFO-{$majorMinor}.md";

// The release branch to verify. Defaults to "<prefix>.x" (e.g. 6.7.12 → 6.7.12.x); an optional
// second argument overrides it. We compare against the remote ref, independent of the checkout.
$targetBranch = $_SERVER['argv'][2] ?? ($versionPrefix . '.x');
$branchRef = 'origin/' . $targetBranch;

if (!refExists(TRUNK_REF)) {
    fwrite(\STDERR, 'ERROR: ' . TRUNK_REF . " not found — fetch it first (git fetch origin trunk).\n");
    exit(1);
}
if (!refExists($branchRef)) {
    fwrite(\STDERR, "ERROR: release branch {$branchRef} not found — fetch it first (git fetch origin {$targetBranch}).\n");
    exit(1);
}

// When running in GitHub Actions, build a base URL so commit SHAs can be rendered as links.
$commitUrlBase = '';
$server = getenv('GITHUB_SERVER_URL');
$repo = getenv('GITHUB_REPOSITORY');
if (\is_string($server) && $server !== '' && \is_string($repo) && $repo !== '') {
    $commitUrlBase = rtrim($server, '/') . '/' . $repo . '/commit';
}

echo "Verifying RELEASE_INFO for {$versionPrefix}.*\n";
echo '  trunk  : ' . TRUNK_REF . "\n";
echo "  branch : {$branchRef}\n";
echo "  file   : {$releaseInfoFile}\n\n";

// Trunk is the authoritative source for what is supposed to be in the release.
// The release branch is what actually shipped (or will ship).
$trunkContent = (string) shell_exec(sprintf('git show %s', escapeshellarg(TRUNK_REF . ':' . $releaseInfoFile)));
$trunkHeadings = extractHeadings($trunkContent, $versionPrefix);
$branchContent = (string) shell_exec(sprintf('git show %s', escapeshellarg($branchRef . ':' . $releaseInfoFile)));
$branchHeadings = extractHeadings($branchContent, $versionPrefix);

if (!$trunkHeadings) {
    echo "No entries found for {$versionPrefix}.* in trunk's {$releaseInfoFile} — nothing to verify.\n";
    exit(0);
}

/** @var list<array{heading: string, sha: string}> $missing */
$missing = [];
/** @var list<array{heading: string, sha: string, note: string}> $warnings */
$warnings = [];
$confirmed = 0;

foreach ($trunkHeadings as $heading) {
    // Check 1 — text: is the heading present in the release branch's copy of the file?
    // If it's missing here, the RELEASE_INFO update was never merged or cherry-picked into the branch.
    $textPresent = \in_array($heading, $branchHeadings, true);

    // Check 2 — commit: find the trunk commit that introduced this heading via pickaxe search (-S),
    // then verify it is a direct ancestor of the release branch.
    // This confirms the full PR (docs + code) landed in the branch, not just the text.
    $introducingCommit = findIntroducingCommit($heading, $releaseInfoFile);
    $commitReachable = false;
    $docsOnly = false;

    if ($introducingCommit !== '') {
        $commitReachable = isAncestor($introducingCommit, $branchRef);

        // Check 3 — docs-only: if the introducing commit only touched RELEASE_INFO and nothing else,
        // it was a standalone documentation update decoupled from the feature code. In that case the
        // reachability check tells us nothing about the actual feature — flag it for manual review.
        $changed = trim((string) shell_exec(sprintf('git diff-tree --no-commit-id -r --name-only %s', escapeshellarg($introducingCommit))));
        $changedFiles = array_values(array_filter(explode("\n", $changed), static fn (string $l): bool => $l !== ''));
        $docsOnly = \count($changedFiles) === 1 && $changedFiles[0] === $releaseInfoFile;
    }

    if ($textPresent && $commitReachable && !$docsOnly) {
        ++$confirmed; // heading and feature code landed together and are reachable
    } elseif (!$textPresent && !$commitReachable) {
        $missing[] = ['heading' => $heading, 'sha' => $introducingCommit];
    } elseif ($textPresent && !$commitReachable) {
        $warnings[] = ['heading' => $heading, 'sha' => $introducingCommit, 'note' => 'RELEASE_INFO present but trunk commit not in branch — verify cherry-pick includes feature code'];
    } elseif (!$textPresent && $commitReachable) {
        $warnings[] = ['heading' => $heading, 'sha' => $introducingCommit, 'note' => 'code commit present but RELEASE_INFO entry missing from branch'];
    } elseif ($docsOnly) {
        $warnings[] = ['heading' => $heading, 'sha' => $introducingCommit, 'note' => 'RELEASE_INFO was updated in a docs-only commit — feature code commit is unknown, verify manually'];
    }
}

$total = \count($trunkHeadings);

// Order entries by the introducing commit hash so log and summary are stable and grouped by commit.
$byCommit = static fn (array $a, array $b): int => strcmp($a['sha'], $b['sha']);
usort($missing, $byCommit);
usort($warnings, $byCommit);

// ── Console log (visible in the Actions job log) ────────────────────────────────
if ($warnings) {
    echo 'WARN: ' . \count($warnings) . " of {$total} entries need manual verification:\n\n";
    foreach ($warnings as $w) {
        echo "  ? {$w['heading']} [" . commitRef($w['sha'], $commitUrlBase) . "] {$w['note']}\n";
    }
    echo "\n";
}

if ($missing) {
    echo 'MISSING: ' . \count($missing) . " of {$total} entries documented on trunk are absent from this release branch:\n\n";
    foreach ($missing as $m) {
        echo "  x {$m['heading']} [" . commitRef($m['sha'], $commitUrlBase) . "]\n";
    }
    echo "\n";
}

// ── Job summary: rendered Markdown with real commit links (always written) ──────
$summaryFile = getenv('GITHUB_STEP_SUMMARY');
if (\is_string($summaryFile) && $summaryFile !== '') {
    file_put_contents(
        $summaryFile,
        renderSummary($versionPrefix, $branchRef, $releaseInfoFile, $total, $confirmed, $missing, $warnings, $commitUrlBase),
        \FILE_APPEND
    );
}

if ($missing) {
    echo "These features were documented in {$releaseInfoFile} on trunk but have not been merged into this release branch.\n";
    exit(1);
}

$ok = $total - \count($warnings);
echo "OK: {$ok} of {$total} entries confirmed present. " . \count($warnings) . " need manual verification (see above).\n";

/**
 * Collects every "### " heading that appears under a "# <version-prefix>.*" section.
 *
 * @return list<string>
 */
function extractHeadings(string $content, string $versionPrefix): array
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

// Returns the most recent trunk commit that changed the count of the given string in the
// given file (i.e. the commit that introduced or last re-added it), or '' if none.
function findIntroducingCommit(string $heading, string $file): string
{
    return trim((string) shell_exec(sprintf(
        'git log %s --format=%s --max-count=1 -S %s -- %s',
        escapeshellarg(TRUNK_REF),
        escapeshellarg('%H'),
        escapeshellarg($heading),
        escapeshellarg($file)
    )));
}

// True when $commit is a direct ancestor of the given ref.
function isAncestor(string $commit, string $ref): bool
{
    exec(sprintf('git merge-base --is-ancestor %s %s 2>/dev/null', escapeshellarg($commit), escapeshellarg($ref)), $output, $code);

    return $code === 0;
}

// True when the given git ref resolves to a commit (e.g. it has been fetched).
function refExists(string $ref): bool
{
    exec(sprintf('git rev-parse --verify --quiet %s 2>/dev/null', escapeshellarg($ref)), $output, $code);

    return $code === 0;
}

// Commit reference for the log: short SHA locally, "short (full-url)" inside GitHub Actions.
function commitRef(string $sha, string $base): string
{
    if ($sha === '') {
        return 'unknown';
    }

    $short = substr($sha, 0, 8);

    return $base !== '' ? sprintf('%s (%s/%s)', $short, $base, $sha) : $short;
}

// Commit reference as a Markdown link for the job summary (code span when unknown).
function commitMd(string $sha, string $base): string
{
    if ($sha === '') {
        return '`unknown`';
    }

    $short = substr($sha, 0, 8);

    return $base !== '' ? sprintf('[`%s`](%s/%s)', $short, $base, $sha) : sprintf('`%s`', $short);
}

// Strip the leading "### " from a heading and escape pipes for Markdown table cells.
function mdTitle(string $heading): string
{
    return str_replace('|', '\\|', (string) preg_replace('/^###[[:space:]]+/', '', $heading));
}

/**
 * @param list<array{heading: string, sha: string}>               $missing
 * @param list<array{heading: string, sha: string, note: string}> $warnings
 */
function renderSummary(string $versionPrefix, string $branch, string $file, int $total, int $confirmed, array $missing, array $warnings, string $base): string
{
    $lines = [];
    $lines[] = "## Release content verification — `{$versionPrefix}.*`";
    $lines[] = '';
    $lines[] = sprintf('**%d** confirmed · **%d** warning(s) · **%d** missing (of %d)', $confirmed, \count($warnings), \count($missing), $total);
    $lines[] = '';
    $lines[] = "- branch: `{$branch}`";
    $lines[] = "- file: `{$file}`";
    $lines[] = '';

    if ($missing) {
        $lines[] = '### ❌ Missing from this release branch';
        $lines[] = '';
        $lines[] = '| Entry | Trunk commit |';
        $lines[] = '| --- | --- |';
        foreach ($missing as $m) {
            $lines[] = sprintf('| %s | %s |', mdTitle($m['heading']), commitMd($m['sha'], $base));
        }
        $lines[] = '';
    }

    if ($warnings) {
        $lines[] = '### ⚠️ Needs manual verification';
        $lines[] = '';
        $lines[] = '| Entry | Trunk commit | Note |';
        $lines[] = '| --- | --- | --- |';
        foreach ($warnings as $w) {
            $lines[] = sprintf('| %s | %s | %s |', mdTitle($w['heading']), commitMd($w['sha'], $base), $w['note']);
        }
        $lines[] = '';
    }

    if (!$missing && !$warnings) {
        $lines[] = "✅ All {$total} documented entries are present and traceable.";
    }

    return implode("\n", $lines) . "\n";
}
