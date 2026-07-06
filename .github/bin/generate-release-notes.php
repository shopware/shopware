<?php

declare(strict_types=1);

// CLI argument parser
$argv = $_SERVER['argv'];
$options = [];
$args = [];

for ($i = 1; $i < count($argv); ++$i) {
    if (str_starts_with($argv[$i], '--')) {
        $key = ltrim($argv[$i], '-');
        // Argument was given as key=value
        if (str_contains($key, '=')) {
            $splittedArg = explode('=', $key);
            $key = $splittedArg[0];
            $options[$key] = $splittedArg[1];
        } elseif (isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '--')) {
            $options[$key] = $argv[++$i];
        } else {
            $options[$key] = true;
        }
    } elseif (str_starts_with($argv[$i], '-')) {
        $options[$argv[$i]] = true;
    } else {
        $args[] = $argv[$i];
    }
}

$version = $args[0] ?? null;
$inputFile = $options['input-file'] ?? 'RELEASE_INFO-6.7.md';
$outputFile = $options['output-file'] ?? null;

if (!$version) {
    fwrite(\STDERR, "Usage: $argv[0] [--input-file=FILE] [--output-file=FILE] <version>\n");
    exit(1);
}

// Gather release info section from input file
$releaseInfo = gatherReleaseInfo($version, $inputFile);
if (!$releaseInfo) {
    fwrite(\STDERR, "Version $version not found in $inputFile\n");
    exit(1);
}

// Build output
$outputContent = "## System requirements\n";
$outputContent .= "* tested on PHP `8.2`, `8.4` and `8.5`\n";
$outputContent .= "* tested on `MySQL 8` and `MariaDB 11`\n\n";
$outputContent .= $releaseInfo . "\n";

// Get latest tag
$latestTag = shell_exec('gh release list --json tagName,isLatest --jq \'.[] | select(.isLatest == true) | .tagName\'') ?? '';
if (!$latestTag) {
    fwrite(\STDERR, "Couldn't get latest tag from Github\n");
    exit(1);
}

$latestTag = trim($latestTag);

// Get all commits since last release
$commitsRaw = shell_exec(sprintf(
    'git log @ %s --pretty=format:"%%H %%s" --grep="^feat:\|^fix:\|^refactor:\|^revert:"',
    escapeshellarg('^' . $latestTag)
));
if (!$commitsRaw) {
    fwrite(\STDERR, "Couldn't get commits\n");
    exit(1);
}

$outputContent .= "## What's Changed\n";
foreach (explode("\n", $commitsRaw) as $commit) {
    if (preg_match('/^([a-z0-9]+)\s(.*)\s\(#(\d+)\)$/', $commit, $m)) {
        $title = $m[2];
        $prNumber = $m[3];
        $isBackport = preg_match('/backport: \d+\.\d+\.\d+\.[\d+|x]/', $title);
        $prNumberAuthorCheck = $prNumber;
        if ($isBackport) {
            $resolved = resolveBackport($prNumber);
            if ($resolved) {
                $prNumberAuthorCheck = $resolved;
            }
        }
        $author = trim(findAuthor($prNumberAuthorCheck) ?? '');
        $outputContent .= "* $title by @$author [#$prNumber](https://github.com/shopware/shopware/pull/$prNumber)\n";
    }
}

$outputContent .= "\n**Full Changelog**: https://github.com/shopware/shopware/compare/$latestTag...v$version\n\n";
$outputContent .= "## Get in touch\n";
$outputContent .= 'Discuss about decisions, bugs you might stumble upon, etc in our [community discord](https://chat.shopware.com). See you there ;)';

if (!$outputFile) {
    echo $outputContent;
} else {
    file_put_contents($outputFile, $outputContent);
    echo "Release notes for $version generated and written to $outputFile.\n";
}

function gatherReleaseInfo(string $version, string $releaseInfoFile): ?string
{
    $contents = file_get_contents($releaseInfoFile);
    if (!$contents) {
        return null;
    }

    $lines = explode("\n", $contents);
    $found = false;
    $output = [];

    foreach ($lines as $line) {
        // Find "# 6.7.X.Y"
        if (preg_match('/^#\s+([\d]+\.[\d]+\.[\d]+\.[\d]+)(?:\s*\(.*\))?$/', $line, $m)) {
            // Start collecting if headline is target version
            if ($m[1] === $version) {
                $found = true;
            } elseif ($found) {
                // Break when we reach the next version
                break;
            }
            continue;
        }

        if ($found) {
            $output[] = $line;
        }
    }

    if (!$found) {
        return '';
    }

    // Remove empty lines form beginning
    while ($output && $output[0] === '') {
        array_shift($output);
    }

    return implode("\n", $output);
}

function findAuthor(string $prNumber): ?string
{
    $author = shell_exec(sprintf(
        'gh pr view https://github.com/shopware/shopware/pull/%s --json author --jq \'.author.login\'',
        escapeshellarg($prNumber)
    ));

    if (!$author) {
        return null;
    }

    return $author;
}

function resolveBackport(string $prNumber): ?string
{
    $body = shell_exec(sprintf(
        'gh pr view https://github.com/shopware/shopware/pull/%s --json body --jq \'.body\'',
        escapeshellarg($prNumber)
    ));

    if (!$body) {
        return null;
    }

    // Resolve PR number from **Backport:**
    if (preg_match('/^\*\*Backport:\*\* https:\/\/github\.com\/shopware\/shopware\/pull\/(\d+)$/m', trim($body), $m)) {
        return $m[1];
    }

    return null;
}
