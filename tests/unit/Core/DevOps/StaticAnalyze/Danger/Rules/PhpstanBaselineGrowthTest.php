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
    #[TestDox('Fails only when the baseline diff adds more lines than it removes')]
    #[DataProvider('baselineDiffProvider')]
    public function testBaselineGrowth(int $additions, int $deletions, bool $expectFailure): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile('phpstan-baseline.php', File::STATUS_MODIFIED, '', '', $additions, $deletions),
        ])));

        (new PhpstanBaselineGrowth())($context);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('not allowed to add new ignored PHPStan errors', $context->getFailures()[0]);
        }
    }

    public static function baselineDiffProvider(): \Generator
    {
        yield 'net growth fails' => [10, 2, true];
        yield 'reshuffling with equal additions and deletions passes' => [5, 5, false];
        yield 'net shrink passes' => [2, 10, false];
        yield 'pure removal passes' => [0, 10, false];
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
