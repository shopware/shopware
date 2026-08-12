<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\Release;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Release\ReleaseContentVerifier;
use Shopware\Core\DevOps\Release\ReleaseSummaryRenderer;
use Shopware\Core\DevOps\Release\VerificationResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class ReleaseSummaryRendererTest extends TestCase
{
    private const URL_BASE = 'https://github.com/shopware/shopware/commit';

    public function testConsoleReportForAllConfirmedShowsOnlyTheOkLine(): void
    {
        $report = (new ReleaseSummaryRenderer())->consoleReport(new VerificationResult(3, 3, [], []), 'RELEASE_INFO-6.7.md');

        static::assertStringContainsString('OK: 3 of 3 entries confirmed present. 0 need manual verification', $report);
        static::assertStringNotContainsString('WARN', $report);
        static::assertStringNotContainsString('MISSING', $report);
    }

    public function testConsoleReportRendersWarningsWithLinkedCommitAndKeepsTheOkLine(): void
    {
        $result = new VerificationResult(2, 1, [], [
            ['heading' => '### Feature A', 'sha' => 'aaaa11112222', 'note' => ReleaseContentVerifier::NOTE_DOCS_ONLY],
        ]);

        $report = (new ReleaseSummaryRenderer(self::URL_BASE))->consoleReport($result, 'RELEASE_INFO-6.7.md');

        static::assertStringContainsString('WARN: 1 of 2 entries need manual verification:', $report);
        static::assertStringContainsString('? ### Feature A [aaaa1111 (' . self::URL_BASE . '/aaaa11112222)] ' . ReleaseContentVerifier::NOTE_DOCS_ONLY, $report);
        static::assertStringContainsString('OK: 1 of 2 entries confirmed present. 1 need manual verification', $report);
    }

    public function testConsoleReportForMissingShowsTheFailureEpilogueAndNoOkLine(): void
    {
        $result = new VerificationResult(1, 0, [['heading' => '### Feature A', 'sha' => '']], []);

        $report = (new ReleaseSummaryRenderer())->consoleReport($result, 'RELEASE_INFO-6.7.md');

        static::assertStringContainsString('MISSING: 1 of 1 entries documented on trunk are absent', $report);
        // unknown sha renders as "unknown", and without a URL base there is no link
        static::assertStringContainsString('x ### Feature A [unknown]', $report);
        static::assertStringContainsString('These features were documented in RELEASE_INFO-6.7.md on trunk', $report);
        static::assertStringNotContainsString('OK:', $report);
    }

    public function testMarkdownSummaryForCleanRunShowsTheSuccessLine(): void
    {
        $markdown = (new ReleaseSummaryRenderer())->markdownSummary(
            '6.7.11',
            'origin/6.7.11.x',
            'RELEASE_INFO-6.7.md',
            new VerificationResult(2, 2, [], []),
        );

        static::assertStringContainsString('## Release content verification — `6.7.11.*`', $markdown);
        static::assertStringContainsString('**2** confirmed · **0** warning(s) · **0** missing (of 2)', $markdown);
        static::assertStringContainsString('✅ All 2 documented entries are present and traceable.', $markdown);
    }

    public function testMarkdownSummaryRendersMissingTableWithLinkedCommitAndEscapedPipes(): void
    {
        $result = new VerificationResult(1, 0, [['heading' => '### Feature | A', 'sha' => 'aaaa11112222']], []);

        $markdown = (new ReleaseSummaryRenderer(self::URL_BASE))->markdownSummary('6.7.11', 'origin/6.7.11.x', 'RELEASE_INFO-6.7.md', $result);

        static::assertStringContainsString('### ❌ Missing from this release branch', $markdown);
        static::assertStringContainsString('| Feature \\| A | [`aaaa1111`](' . self::URL_BASE . '/aaaa11112222) |', $markdown);
    }

    public function testMarkdownSummaryRendersUnknownCommitAsCodeSpanWithoutUrlBase(): void
    {
        $result = new VerificationResult(1, 0, [], [
            ['heading' => '### Feature A', 'sha' => '', 'note' => ReleaseContentVerifier::NOTE_TEXT_WITHOUT_COMMIT],
        ]);

        $markdown = (new ReleaseSummaryRenderer())->markdownSummary('6.7.11', 'origin/6.7.11.x', 'RELEASE_INFO-6.7.md', $result);

        static::assertStringContainsString('### ⚠️ Needs manual verification', $markdown);
        static::assertStringContainsString('| Feature A | `unknown` | ' . ReleaseContentVerifier::NOTE_TEXT_WITHOUT_COMMIT . ' |', $markdown);
    }

    public function testCommitStatusDescriptionForNothingToVerify(): void
    {
        $description = (new ReleaseSummaryRenderer())->commitStatusDescription(new VerificationResult(0, 0, [], []), '6.7.11', '6.7.11.x');

        static::assertSame('no entries for 6.7.11.* on trunk — nothing to verify', $description);
    }

    public function testCommitStatusDescriptionForMissingEntries(): void
    {
        $result = new VerificationResult(3, 1, [
            ['heading' => '### A', 'sha' => 'aaaa'],
            ['heading' => '### B', 'sha' => 'bbbb'],
        ], []);

        $description = (new ReleaseSummaryRenderer())->commitStatusDescription($result, '6.7.11', '6.7.11.x');

        static::assertSame('2 of 3 documented entries missing from 6.7.11.x', $description);
    }

    public function testCommitStatusDescriptionForConfirmedRun(): void
    {
        $result = new VerificationResult(4, 3, [], [
            ['heading' => '### A', 'sha' => 'aaaa', 'note' => ReleaseContentVerifier::NOTE_DOCS_ONLY],
        ]);

        $description = (new ReleaseSummaryRenderer())->commitStatusDescription($result, '6.7.11', '6.7.11.x');

        static::assertSame('3 of 4 entries confirmed, 1 need manual verification', $description);
    }
}
