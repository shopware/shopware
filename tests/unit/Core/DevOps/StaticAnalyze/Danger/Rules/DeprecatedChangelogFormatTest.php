<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\DeprecatedChangelogFormat;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeprecatedChangelogFormat::class)]
class DeprecatedChangelogFormatTest extends TestCase
{
    #[TestDox('Only markdown files under changelog/_unreleased/ count as the old changelog format')]
    #[DataProvider('changelogFileProvider')]
    public function testDetectsOldChangelogFormat(string $fileName, bool $expectFailure): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([new StubFile($fileName)])));

        (new DeprecatedChangelogFormat())($context);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('old changelog format', $context->getFailures()[0]);
        }
    }

    public static function changelogFileProvider(): \Generator
    {
        yield 'markdown file in the old unreleased changelog directory fails' => ['changelog/_unreleased/2026-01-01-my-change.md', true];
        yield 'released changelog entries are not flagged' => ['changelog/release-6-7-1-0/2026-01-01-old.md', false];
        yield 'non-markdown file under _unreleased is not flagged' => ['changelog/_unreleased/notes.txt', false];
        yield 'the new central release info file is not flagged' => ['RELEASE_INFO-6.7.md', false];
    }
}
