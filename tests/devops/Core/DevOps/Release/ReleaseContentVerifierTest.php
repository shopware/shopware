<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\Release;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Release\ReleaseContentVerifier;
use Shopware\Core\DevOps\Release\VerificationResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ReleaseContentVerifierTest extends TestCase
{
    private const VERSION = '6.7.11';
    private const FILE = 'RELEASE_INFO-6.7.md';
    private const TRUNK = 'origin/trunk';
    private const BRANCH = 'origin/6.7.11.x';

    public function testHeadingWithReachableFeatureCommitIsConfirmed(): void
    {
        $content = $this->releaseInfo('### Feature A');

        $git = new FakeGitReader(
            files: [self::TRUNK . ':' . self::FILE => $content, self::BRANCH . ':' . self::FILE => $content],
            introducing: ['### Feature A' => 'aaaa1111'],
            ancestors: ['aaaa1111'],
            changedFiles: ['aaaa1111' => [self::FILE, 'src/Feature.php']],
        );

        $result = $this->verify($git);

        static::assertSame(1, $result->total);
        static::assertSame(1, $result->confirmed);
        static::assertSame([], $result->missing);
        static::assertSame([], $result->warnings);
        static::assertFalse($result->hasMissing());
    }

    public function testHeadingAbsentFromBranchWithUnreachableCommitIsMissing(): void
    {
        $git = new FakeGitReader(
            files: [
                self::TRUNK . ':' . self::FILE => $this->releaseInfo('### Feature A'),
                self::BRANCH . ':' . self::FILE => $this->releaseInfo(),
            ],
            introducing: ['### Feature A' => 'aaaa1111'],
            // not an ancestor of the branch
            changedFiles: ['aaaa1111' => [self::FILE, 'src/Feature.php']],
        );

        $result = $this->verify($git);

        static::assertSame(1, $result->total);
        static::assertSame(0, $result->confirmed);
        static::assertTrue($result->hasMissing());
        static::assertSame([['heading' => '### Feature A', 'sha' => 'aaaa1111']], $result->missing);
    }

    public function testHeadingPresentButCommitNotReachableIsWarned(): void
    {
        $content = $this->releaseInfo('### Feature A');

        $git = new FakeGitReader(
            files: [self::TRUNK . ':' . self::FILE => $content, self::BRANCH . ':' . self::FILE => $content],
            introducing: ['### Feature A' => 'aaaa1111'],
            // commit exists but is not an ancestor of the branch
            changedFiles: ['aaaa1111' => [self::FILE, 'src/Feature.php']],
        );

        $result = $this->verify($git);

        static::assertSame([], $result->missing);
        static::assertSame(
            [['heading' => '### Feature A', 'sha' => 'aaaa1111', 'note' => ReleaseContentVerifier::NOTE_TEXT_WITHOUT_COMMIT]],
            $result->warnings,
        );
    }

    public function testCommitReachableButHeadingMissingFromBranchIsWarned(): void
    {
        $git = new FakeGitReader(
            files: [
                self::TRUNK . ':' . self::FILE => $this->releaseInfo('### Feature A'),
                self::BRANCH . ':' . self::FILE => $this->releaseInfo(),
            ],
            introducing: ['### Feature A' => 'aaaa1111'],
            ancestors: ['aaaa1111'],
            changedFiles: ['aaaa1111' => [self::FILE, 'src/Feature.php']],
        );

        $result = $this->verify($git);

        static::assertSame(
            [['heading' => '### Feature A', 'sha' => 'aaaa1111', 'note' => ReleaseContentVerifier::NOTE_COMMIT_WITHOUT_TEXT]],
            $result->warnings,
        );
    }

    public function testDocsOnlyIntroducingCommitIsWarned(): void
    {
        $content = $this->releaseInfo('### Feature A');

        $git = new FakeGitReader(
            files: [self::TRUNK . ':' . self::FILE => $content, self::BRANCH . ':' . self::FILE => $content],
            introducing: ['### Feature A' => 'aaaa1111'],
            ancestors: ['aaaa1111'],
            // the introducing commit touched only RELEASE_INFO
            changedFiles: ['aaaa1111' => [self::FILE]],
        );

        $result = $this->verify($git);

        static::assertSame(0, $result->confirmed);
        static::assertSame(
            [['heading' => '### Feature A', 'sha' => 'aaaa1111', 'note' => ReleaseContentVerifier::NOTE_DOCS_ONLY]],
            $result->warnings,
        );
    }

    public function testNoHeadingsOnTrunkYieldsNothingToVerify(): void
    {
        $git = new FakeGitReader(
            files: [self::TRUNK . ':' . self::FILE => "# 6.7.11.0\n\nno feature headings here\n"],
        );

        $result = $this->verify($git);

        static::assertSame(0, $result->total);
        static::assertSame([], $result->missing);
        static::assertSame([], $result->warnings);
    }

    public function testEntriesAreSortedByIntroducingCommit(): void
    {
        $content = $this->releaseInfo('### Feature Z', '### Feature A');

        $git = new FakeGitReader(
            files: [self::TRUNK . ':' . self::FILE => $content, self::BRANCH . ':' . self::FILE => $this->releaseInfo()],
            introducing: ['### Feature Z' => 'zzzz9999', '### Feature A' => 'aaaa1111'],
            changedFiles: ['zzzz9999' => [self::FILE, 'src/Z.php'], 'aaaa1111' => [self::FILE, 'src/A.php']],
        );

        $result = $this->verify($git);

        static::assertSame(['aaaa1111', 'zzzz9999'], array_column($result->missing, 'sha'));
    }

    public function testExtractHeadingsCollectsThirdLevelHeadingsWithinTheVersionSection(): void
    {
        $content = "# 6.7.11.0\n\n### Feature A\nbody\n#### deeper\n## other\n### Feature B\n";

        static::assertSame(
            ['### Feature A', '### Feature B'],
            ReleaseContentVerifier::extractHeadings($content, self::VERSION),
        );
    }

    public function testExtractHeadingsStopsAtTheNextTopLevelSection(): void
    {
        $content = "# 6.7.11.0\n### In section\n# 6.8.0.0\n### Other version\n";

        static::assertSame(['### In section'], ReleaseContentVerifier::extractHeadings($content, self::VERSION));
    }

    public function testExtractHeadingsSpansMultipleSectionsSharingThePrefix(): void
    {
        $content = "# 6.7.11.0\n### First patch\n# 6.7.11.1\n### Second patch\n";

        static::assertSame(
            ['### First patch', '### Second patch'],
            ReleaseContentVerifier::extractHeadings($content, self::VERSION),
        );
    }

    private function releaseInfo(string ...$headings): string
    {
        return "# 6.7.11.0\n\n" . implode("\n", array_map(static fn (string $h): string => $h . "\nsome description\n", $headings));
    }

    private function verify(FakeGitReader $git): VerificationResult
    {
        return (new ReleaseContentVerifier($git))->verify(self::VERSION, self::TRUNK, self::BRANCH, self::FILE);
    }
}
