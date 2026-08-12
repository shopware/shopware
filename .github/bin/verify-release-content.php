<?php declare(strict_types=1);

use Shopware\Core\DevOps\Release\ProcessGitReader;
use Shopware\Core\DevOps\Release\ReleaseContentVerifier;
use Shopware\Core\DevOps\Release\ReleaseSummaryRenderer;
use Symfony\Component\Filesystem\Filesystem;

// Thin CLI wrapper around the release-content verification. All logic lives in
// Shopware\Core\DevOps\Release\* (unit-tested); this script only handles argv, wiring and output.
//
// Usage: php verify-release-content.php <version-prefix> [release-branch]
// Example: php verify-release-content.php 6.7.11
// Example: php verify-release-content.php 6.7.11 6.7.11.x
//
// The verdict is NOT signalled through the exit code. It is written to GITHUB_OUTPUT as
// state=success|failure plus a one-line description, and the workflow publishes it as the
// `release-content/verify` commit status. Missing entries keep the job green on purpose — the
// commit status is the gate. An operational error (bad usage, refs not fetched) throws, so a red
// job always means the verification itself could not run.

require __DIR__ . '/../../vendor/autoload.php';

// Operational errors (bad usage, refs not fetched) are thrown, not exited. An uncaught exception
// leaves with a non-zero status, so the job turns red without this script ever calling exit().

(static function (): void {
    $trunkRef = 'origin/trunk';

    $versionPrefix = $_SERVER['argv'][1] ?? '';
    if (!\is_string($versionPrefix) || $versionPrefix === '') {
        throw new RuntimeException(\sprintf('Usage: %s <version-prefix> [release-branch]', $_SERVER['argv'][0]));
    }

    $majorMinor = implode('.', \array_slice(explode('.', $versionPrefix), 0, 2));
    $releaseInfoFile = "RELEASE_INFO-{$majorMinor}.md";

    // The release branch to verify. Defaults to "<prefix>.x" (e.g. 6.7.12 → 6.7.12.x); an optional
    // second argument overrides it. We compare against the remote ref, independent of the checkout.
    $targetBranch = $_SERVER['argv'][2] ?? ($versionPrefix . '.x');
    \assert(\is_string($targetBranch));
    $branchRef = 'origin/' . $targetBranch;

    $git = new ProcessGitReader();

    if (!$git->refExists($trunkRef)) {
        throw new RuntimeException($trunkRef . ' not found — fetch it first (git fetch origin trunk).');
    }
    if (!$git->refExists($branchRef)) {
        throw new RuntimeException("release branch {$branchRef} not found — fetch it first (git fetch origin {$targetBranch}).");
    }

    // When running in GitHub Actions, build a base URL so commit SHAs can be rendered as links.
    $commitUrlBase = '';
    $server = getenv('GITHUB_SERVER_URL');
    $repo = getenv('GITHUB_REPOSITORY');
    if (\is_string($server) && $server !== '' && \is_string($repo) && $repo !== '') {
        $commitUrlBase = rtrim($server, '/') . '/' . $repo . '/commit';
    }

    echo "Verifying RELEASE_INFO for {$versionPrefix}.*\n";
    echo "  trunk  : {$trunkRef}\n";
    echo "  branch : {$branchRef}\n";
    echo "  file   : {$releaseInfoFile}\n\n";

    $result = (new ReleaseContentVerifier($git))->verify($versionPrefix, $trunkRef, $branchRef, $releaseInfoFile);
    $renderer = new ReleaseSummaryRenderer($commitUrlBase);
    $filesystem = new Filesystem();

    if ($result->total === 0) {
        echo "No entries found for {$versionPrefix}.* in trunk's {$releaseInfoFile} — nothing to verify.\n";
    } else {
        echo $renderer->consoleReport($result, $releaseInfoFile);

        // Job summary: rendered Markdown with real commit links (written whenever GitHub provides the file).
        $summaryFile = getenv('GITHUB_STEP_SUMMARY');
        if (\is_string($summaryFile) && $summaryFile !== '') {
            $filesystem->appendToFile($summaryFile, $renderer->markdownSummary($versionPrefix, $branchRef, $releaseInfoFile, $result));
        }
    }

    // Deliver the verdict as step outputs so the workflow can publish the commit status.
    $outputFile = getenv('GITHUB_OUTPUT');
    if (\is_string($outputFile) && $outputFile !== '') {
        $state = $result->hasMissing() ? 'failure' : 'success';
        $description = $renderer->commitStatusDescription($result, $versionPrefix, $targetBranch);
        $filesystem->appendToFile($outputFile, "state={$state}\ndescription={$description}\n");
    }
})();
