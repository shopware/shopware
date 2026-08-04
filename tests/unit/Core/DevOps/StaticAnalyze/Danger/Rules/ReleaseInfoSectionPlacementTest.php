<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\ReleaseInfoSectionPlacement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReleaseInfoSectionPlacement::class)]
class ReleaseInfoSectionPlacementTest extends TestCase
{
    private const RELEASE_INFO = 'RELEASE_INFO-6.7.md';

    /**
     * Three version sections, newest first: the upcoming one starts on line 1, the branched-off
     * 6.7.13.0 on line 9 and the released 6.7.12.0 on line 17.
     */
    private const CONTENT = <<<'MD'
        # 6.7.14.0 (upcoming)

        ## Features

        ### Entry in the upcoming section

        Description.

        # 6.7.13.0

        ## Core

        ### Entry in the branched off section

        Description.

        # 6.7.12.0

        ### Entry in the released section

        Description.
        MD;

    #[TestDox('Stays silent when the pull request does not touch a release info file')]
    public function testSilentWithoutReleaseInfoFile(): void
    {
        $context = $this->createContext([new StubFile('src/Core/Framework/Framework.php')]);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Stays silent when the entry is added to the upcoming section')]
    public function testSilentForEntryInUpcomingSection(): void
    {
        // Adds "### Entry in the upcoming section" on line 5, inside "# 6.7.14.0 (upcoming)".
        $patch = <<<'DIFF'
            @@ -3,3 +3,5 @@
             ## Features

            +### Entry in the upcoming section
            +
             Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch)]);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Warns when the entry is added to a section that has already been branched off')]
    public function testWarnsForEntryInBranchedOffSection(): void
    {
        // Adds "### Entry in the branched off section" on line 13, inside "# 6.7.13.0".
        $patch = <<<'DIFF'
            @@ -11,3 +11,5 @@
             ## Core

            +### Entry in the branched off section
            +
             Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch)]);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertCount(1, $context->getWarnings());

        $warning = $context->getWarnings()[0];
        static::assertStringContainsString('frozen section of `RELEASE_INFO-6.7.md`', $warning);
        static::assertStringContainsString('* "Entry in the branched off section" — added under `# 6.7.13.0`', $warning);
        static::assertStringContainsString('`# 6.7.14.0` is the only section that still accepts new entries', $warning);
    }

    #[TestDox('Reports every misplaced entry of a pull request in a single warning')]
    public function testWarnsOnceForSeveralMisplacedEntries(): void
    {
        // Adds an entry on line 13, inside "# 6.7.13.0", and another on line 19, inside "# 6.7.12.0".
        $patch = <<<'DIFF'
            @@ -11,3 +11,5 @@
             ## Core

            +### Entry in the branched off section
            +
             Description.
            @@ -17,3 +19,5 @@
             # 6.7.12.0

            +### Entry in the released section
            +
             Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch)]);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertCount(1, $context->getWarnings());

        $warning = $context->getWarnings()[0];
        static::assertStringContainsString('* "Entry in the branched off section" — added under `# 6.7.13.0`', $warning);
        static::assertStringContainsString('* "Entry in the released section" — added under `# 6.7.12.0`', $warning);
    }

    #[TestDox('Stays silent when a released entry is only reworded, because no entry heading is added')]
    public function testSilentForRewordedEntry(): void
    {
        $patch = <<<'DIFF'
            @@ -19,3 +19,3 @@
             ### Entry in the released section

            -Descriptoin.
            +Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch)]);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Stays silent when a misplaced entry is moved into the upcoming section')]
    public function testSilentWhenEntryIsMovedIntoUpcomingSection(): void
    {
        $content = <<<'MD'
            # 6.7.14.0 (upcoming)

            ## Core

            ### Moved entry

            Description.

            # 6.7.13.0
            MD;

        // Removes the entry from "# 6.7.13.0" and re-adds it on line 5, inside the upcoming section.
        $patch = <<<'DIFF'
            @@ -3,2 +3,6 @@
             ## Core

            +### Moved entry
            +
            +Description.
            +
            @@ -5,5 +9,1 @@
             # 6.7.13.0
            -
            -### Moved entry
            -
            -Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch, $content)]);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Treats the topmost section as open once the upcoming marker has been dropped for the release')]
    public function testTopmostSectionIsOpenWithoutUpcomingMarker(): void
    {
        $content = <<<'MD'
            # 6.7.13.1

            ## Core

            ### Entry for the patch release

            Description.

            # 6.7.13.0

            ### Entry in the released section

            Description.
            MD;

        $patch = <<<'DIFF'
            @@ -3,3 +3,5 @@
             ## Core

            +### Entry for the patch release
            +
             Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch, $content)]);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Ignores headings deeper than an entry heading')]
    public function testIgnoresDeeperHeadings(): void
    {
        $patch = <<<'DIFF'
            @@ -11,3 +11,5 @@
             ## Core

            +#### Detail of the branched off entry
            +
             Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch)]);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Stays silent when the pull request only removes an entry')]
    public function testSilentForRemovedEntry(): void
    {
        $patch = <<<'DIFF'
            @@ -13,3 +12,1 @@
            -### Entry in the branched off section
            -
             Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch)]);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Stays silent when the patch is unavailable')]
    public function testSilentWithoutPatch(): void
    {
        $context = $this->createContext([$this->releaseInfoFile('')]);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Ignores a deleted release info file')]
    public function testIgnoresRemovedReleaseInfoFile(): void
    {
        $patch = <<<'DIFF'
            @@ -11,3 +11,5 @@
             ## Core

            +### Entry in the branched off section
            +
             Description.
            DIFF;

        $file = new StubFile(self::RELEASE_INFO, File::STATUS_REMOVED, self::CONTENT, $patch);
        $context = $this->createContext([$file]);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Warns when the milestone label names a version that has no section yet')]
    public function testWarnsForMissingSectionOfTheMilestone(): void
    {
        $content = <<<'MD'
            # 6.7.13.0 (upcoming)

            ## Features

            ### Entry in the upcoming section

            Description.
            MD;

        $patch = <<<'DIFF'
            @@ -3,3 +3,5 @@
             ## Features

            +### Entry in the upcoming section
            +
             Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch, $content)], ['milestone/6.7.14.0']);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertCount(1, $context->getWarnings());

        $warning = $context->getWarnings()[0];
        static::assertStringContainsString('`milestone/6.7.14.0` label ships this pull request with **6.7.14.0**', $warning);
        static::assertStringContainsString('newest section in `RELEASE_INFO-6.7.md` is `# 6.7.13.0`', $warning);
        static::assertStringContainsString('add a `# 6.7.14.0 (upcoming)` section', $warning);
    }

    #[TestDox('Points at the stale label when the milestone names a version that is already branched off')]
    public function testWarnsAboutStaleMilestoneLabel(): void
    {
        // A pull request opened before the branch-off keeps its old milestone label: both MCP pull
        // requests that caused this still carried `milestone/6.7.13.0` when they shipped with 6.7.14.0.
        $patch = <<<'DIFF'
            @@ -3,3 +3,5 @@
             ## Features

            +### Entry in the upcoming section
            +
             Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch)], ['milestone/6.7.13.0']);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertCount(1, $context->getWarnings());

        $warning = $context->getWarnings()[0];
        static::assertStringContainsString('`milestone/6.7.13.0` label puts this pull request into **6.7.13.0**', $warning);
        static::assertStringContainsString('`# 6.7.13.0` has already been branched off', $warning);
        static::assertStringContainsString('The label is most likely left over from before the branch-off', $warning);
        // Recreating the frozen section is never the fix for a stale label.
        static::assertStringNotContainsString('(upcoming)` section at the top', $warning);
    }

    #[TestDox('Stays silent when the milestone label matches the upcoming section')]
    public function testSilentWhenMilestoneMatchesUpcomingSection(): void
    {
        $patch = <<<'DIFF'
            @@ -3,3 +3,5 @@
             ## Features

            +### Entry in the upcoming section
            +
             Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch)], ['milestone/6.7.14.0']);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Ignores a milestone label of another minor, because its release info file is a different one')]
    public function testIgnoresMilestoneOfAnotherMinor(): void
    {
        $patch = <<<'DIFF'
            @@ -3,3 +3,5 @@
             ## Features

            +### Entry in the upcoming section
            +
             Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch)], ['milestone/6.8.0.0']);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Skips the milestone cross-check while several sections are marked as upcoming')]
    public function testSkipsMilestoneCheckForSeveralUpcomingSections(): void
    {
        $content = <<<'MD'
            # 6.7.14.0 (upcoming)

            ## Features

            ### Entry in the upcoming section

            Description.

            # 6.7.13.0 (upcoming)
            MD;

        $patch = <<<'DIFF'
            @@ -3,3 +3,5 @@
             ## Features

            +### Entry in the upcoming section
            +
             Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch, $content)], ['milestone/6.7.15.0']);

        (new ReleaseInfoSectionPlacement())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('The checked release info file pattern is configurable')]
    public function testConfigurableReleaseInfoFilePattern(): void
    {
        $patch = <<<'DIFF'
            @@ -11,3 +11,5 @@
             ## Core

            +### Entry in the branched off section
            +
             Description.
            DIFF;

        $context = $this->createContext([$this->releaseInfoFile($patch)]);

        (new ReleaseInfoSectionPlacement('RELEASE_INFO-6.8.md'))($context);

        static::assertFalse($context->hasReports());
    }

    private function releaseInfoFile(string $patch, ?string $content = null): StubFile
    {
        return new StubFile(self::RELEASE_INFO, File::STATUS_MODIFIED, $content ?? self::CONTENT, $patch);
    }

    /**
     * @param list<File> $files
     * @param list<string> $labels
     */
    private function createContext(array $files, array $labels = []): Context
    {
        return new Context(new StubPlatform(new StubPullRequest($files, [], $labels)));
    }
}
