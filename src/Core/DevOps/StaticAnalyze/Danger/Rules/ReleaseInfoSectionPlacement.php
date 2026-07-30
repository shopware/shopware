<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * A release info file collects new entries in exactly one section: the topmost version, marked
 * `(upcoming)`. Every section below it has already been branched off or released and is frozen.
 *
 * An entry added to a frozen section never reaches the release notes of that version, because those
 * are generated from the release branch. It only shows up later as drift between trunk and the
 * release branch, which is confusing for everyone reading trunk in the meantime.
 *
 * This happens on merge, not on purpose: a pull request opened before the branch-off writes into the
 * section that was still upcoming back then, and the merge keeps it there.
 *
 * @internal
 */
#[Package('framework')]
class ReleaseInfoSectionPlacement
{
    public function __construct(private readonly string $releaseInfoFilePattern = 'RELEASE_INFO-*.md')
    {
    }

    public function __invoke(Context $context): void
    {
        $pullRequest = $context->platform->pullRequest;

        $releaseInfoFiles = $pullRequest->getFiles()
            ->matches($this->releaseInfoFilePattern)
            ->filter(static fn (File $file): bool => $file->status !== File::STATUS_REMOVED);

        foreach ($releaseInfoFiles as $file) {
            $this->checkFile($context, $file, $pullRequest->labels);
        }
    }

    /**
     * @param array<string> $labels
     */
    private function checkFile(Context $context, File $file, array $labels): void
    {
        // Only a new "### " heading starts a new entry. Keying on those keeps edits to existing
        // entries — typo and wording fixes in released sections — out of the check.
        $addedEntries = $this->addedEntries($file->patch);
        if ($addedEntries === []) {
            return;
        }

        $sections = $this->versionSections($file->getContent());
        if ($sections === []) {
            return;
        }

        $openSections = array_values(array_filter($sections, static fn (array $section): bool => $section['upcoming']));

        // The section that accepts new entries: the one marked "(upcoming)", or — once that marker has
        // been dropped for the release — the topmost one, since the file is ordered newest first.
        $openSection = $openSections[0] ?? $sections[0];

        $this->reportMisplacedEntries($context, $file, $sections, $addedEntries, $openSection['version']);

        // A single unambiguous open section can be cross-checked against the milestone label, which
        // branch-off keeps up to date. A mismatch means the section for that milestone is missing.
        if (\count($openSections) <= 1) {
            $this->reportMissingSection($context, $file, $openSection['version'], $labels);
        }
    }

    /**
     * @param list<array{version: string, line: int, upcoming: bool}> $sections
     * @param array<int, string> $addedEntries
     */
    private function reportMisplacedEntries(Context $context, File $file, array $sections, array $addedEntries, string $openVersion): void
    {
        $misplaced = [];
        foreach ($addedEntries as $line => $heading) {
            $version = $this->versionOfLine($sections, $line);

            if ($version !== null && $version !== $openVersion) {
                // Entry headings contain code spans themselves, so they are quoted instead of wrapped in backticks.
                $misplaced[] = \sprintf('* "%s" — added under `# %s`', $heading, $version);
            }
        }

        if ($misplaced === []) {
            return;
        }

        $context->warning(
            \sprintf('These entries were added to a frozen section of `%s`:<br/><br/>', $file->name)
            . implode('<br/>', $misplaced)
            . \sprintf('<br/><br/>`# %s` is the only section that still accepts new entries.', $openVersion)
            . ' Every section below it has already been branched off or released, so an entry added there'
            . ' will not appear in the release notes of that version and shows up as drift between'
            . ' `trunk` and the release branch instead.'
            . \sprintf('<br/><br/>Please move them under `# %s`, into the matching category heading.', $openVersion)
            . ' If this entry documents a change that really did ship in the older version, resolve this warning.'
        );
    }

    /**
     * @param array<string> $labels
     */
    private function reportMissingSection(Context $context, File $file, string $openVersion, array $labels): void
    {
        $milestone = $this->milestoneVersion($labels);
        if ($milestone === null || $milestone === $openVersion) {
            return;
        }

        // Only compare within the same minor: during a major transition both release info files
        // exist side by side and the milestone label describes just one of them.
        if ($this->minorOf($milestone) !== $this->minorOf($openVersion)) {
            return;
        }

        $context->warning(
            \sprintf('The `milestone/%s` label ships this pull request with **%s**,', $milestone, $milestone)
            . \sprintf(' but the newest section in `%s` is `# %s`.', $file->name, $openVersion)
            . \sprintf('<br/><br/>Please add a `# %s (upcoming)` section at the top of the file and put your', $milestone)
            . ' entries there, or correct the milestone label if this pull request does ship with'
            . \sprintf(' %s.', $openVersion)
        );
    }

    /**
     * Collects the entry headings added by this pull request, keyed by their line number in the
     * resulting file.
     *
     * @return array<int, string>
     */
    private function addedEntries(string $patch): array
    {
        $entries = [];
        $line = 0;

        foreach (explode("\n", $patch) as $patchLine) {
            // "@@ -203,6 +203,8 @@" — the second number is where this hunk starts in the new file.
            if (preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)/', $patchLine, $hunk) === 1) {
                $line = (int) $hunk[1];
                continue;
            }

            if ($line === 0) {
                continue; // diff header, before the first hunk
            }

            // Removed lines and the "\ No newline at end of file" marker do not exist in the new file.
            if (str_starts_with($patchLine, '-') || str_starts_with($patchLine, '\\')) {
                continue;
            }

            if (preg_match('/^\+###[[:space:]]+(.+?)[[:space:]]*$/', $patchLine, $heading) === 1) {
                $entries[$line] = $heading[1];
            }

            ++$line; // both added and context lines advance the new file
        }

        return $entries;
    }

    /**
     * Collects the version sections of the file, in the order they appear.
     *
     * @return list<array{version: string, line: int, upcoming: bool}>
     */
    private function versionSections(string $content): array
    {
        $sections = [];
        $line = 0;

        foreach (explode("\n", $content) as $contentLine) {
            ++$line;

            // "# 6.7.14.0 (upcoming)". Category headings use "##" and YAML comments inside fenced
            // code blocks never look like a version, so neither is picked up here.
            if (preg_match('/^#[[:space:]]+(\d+\.\d+\.\d+\.\d+)[[:space:]]*(?:\((?<note>[^)]*)\))?[[:space:]]*$/', $contentLine, $matches) !== 1) {
                continue;
            }

            $sections[] = [
                'version' => $matches[1],
                'line' => $line,
                'upcoming' => stripos($matches['note'] ?? '', 'upcoming') !== false,
            ];
        }

        return $sections;
    }

    /**
     * @param list<array{version: string, line: int, upcoming: bool}> $sections
     */
    private function versionOfLine(array $sections, int $line): ?string
    {
        $version = null;
        foreach ($sections as $section) {
            if ($section['line'] > $line) {
                break;
            }

            $version = $section['version'];
        }

        return $version;
    }

    /**
     * @param array<string> $labels
     */
    private function milestoneVersion(array $labels): ?string
    {
        foreach ($labels as $label) {
            if (preg_match('#^milestone/(\d+\.\d+\.\d+\.\d+)$#', $label, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function minorOf(string $version): string
    {
        return implode('.', \array_slice(explode('.', $version), 0, 2));
    }
}
