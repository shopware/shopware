<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Changelog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Changelog\ChangelogParser;

/**
 * @internal
 */
#[CoversClass(ChangelogParser::class)]
class ChangelogParserTest extends TestCase
{
    #[DataProvider('provideRelevantCommitSubjects')]
    public function testRelevantCommitRegexMatchesConventionalCommits(string $subject, ?string $expectedPullRequest): void
    {
        $matched = preg_match('/' . ChangelogParser::RELEVANT_COMMIT_REGEX . '/', $subject, $matches);

        if ($expectedPullRequest === null) {
            static::assertSame(0, $matched, \sprintf('Subject should not match: %s', $subject));

            return;
        }

        static::assertSame(1, $matched, \sprintf('Subject should match: %s', $subject));
        static::assertSame($expectedPullRequest, $matches[3]);
    }

    /**
     * @return iterable<string, array{0: string, 1: string|null}>
     */
    public static function provideRelevantCommitSubjects(): iterable
    {
        yield 'feat with pull request' => ['feat: add a new thing (#123)', '123'];
        yield 'fix with pull request' => ['fix: correct a thing (#456)', '456'];
        yield 'fix with scope' => ['fix(link-category): implement redirect behaviour (#18154)', '18154'];
        yield 'fix with breaking change marker' => ['fix(scope)!: drop a thing (#789)', '789'];
        yield 'feat with breaking change marker' => ['feat!: change a thing (#321)', '321'];
        yield 'backport keeps the merged pull request number' => ['fix: zugferd deliveries (backport #18095) (#18152)', '18152'];
        yield 'backport with colon keeps the merged pull request number' => ['fix: enforce order (backport: 6.6.x) (#18191)', '18191'];

        yield 'other conventional type is ignored' => ['chore: bump dependency (#333)', null];
        yield 'ci type is ignored' => ['ci: post slack message on release (#18112)', null];
        yield 'docs type is ignored' => ['docs: update readme (#444)', null];
        yield 'fix without a pull request reference' => ['fix: correct a thing', null];
        yield 'non conventional subject' => ['NEXT-12345 - fix something (#555)', null];
    }
}
