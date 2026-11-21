<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\PhpstanBaselineGrowth;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PhpstanBaselineGrowth::class)]
class PhpstanBaselineGrowthTest extends TestCase
{
    #[TestDox('Fails only when a baseline diff adds more lines than it removes')]
    #[DataProvider('baselineDiffProvider')]
    public function testBaselineGrowth(string $baseline, int $additions, int $deletions, bool $expectFailure): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($baseline, File::STATUS_MODIFIED, '', '', $additions, $deletions),
        ])));

        (new PhpstanBaselineGrowth())($context);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('not allowed to add new ignored PHPStan errors to `' . $baseline . '`', $context->getFailures()[0]);
        }
    }

    public static function baselineDiffProvider(): \Generator
    {
        foreach (['phpstan-baseline.php', 'phpstan-baseline-architecture.php'] as $baseline) {
            yield $baseline . ': net growth fails' => [$baseline, 10, 2, true];
            yield $baseline . ': reshuffling with equal additions and deletions passes' => [$baseline, 5, 5, false];
            yield $baseline . ': net shrink passes' => [$baseline, 2, 10, false];
            yield $baseline . ': pure removal passes' => [$baseline, 0, 10, false];
        }
    }

    #[TestDox('Reports each growing baseline separately')]
    public function testReportsEachGrowingBaseline(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile('phpstan-baseline.php', File::STATUS_MODIFIED, '', '', 3, 0),
            new StubFile('phpstan-baseline-architecture.php', File::STATUS_MODIFIED, '', '', 3, 0),
        ])));

        (new PhpstanBaselineGrowth())($context);

        static::assertCount(2, $context->getFailures());
    }

    #[TestDox('Stays silent when the baseline is not part of the pull request')]
    public function testSilentWithoutBaselineChange(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([new StubFile('src/Core/Framework/Framework.php')])));

        (new PhpstanBaselineGrowth())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('The skip-danger-phpstan-baseline label disables the check')]
    public function testSkipLabelDisablesTheCheck(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest(
            [new StubFile('phpstan-baseline.php', File::STATUS_MODIFIED, '', '', 10, 0)],
            [],
            ['skip-danger-phpstan-baseline'],
        )));

        (new PhpstanBaselineGrowth())($context);

        static::assertFalse($context->hasReports());
    }
}
