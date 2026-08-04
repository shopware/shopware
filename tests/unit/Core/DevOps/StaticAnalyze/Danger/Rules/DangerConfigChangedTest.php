<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\DangerConfigChanged;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DangerConfigChanged::class)]
class DangerConfigChangedTest extends TestCase
{
    #[TestDox('Notices that Danger config, rule and runner changes do not apply to the same pull request')]
    #[DataProvider('touchedFileProvider')]
    public function testSelfChangeNotice(string $fileName, bool $expectNotice): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([new StubFile($fileName)])));

        (new DangerConfigChanged())($context);

        static::assertSame($expectNotice, $context->hasNotices());
        if ($expectNotice) {
            static::assertStringContainsString('not applied to this pull request\'s own Danger run', $context->getNotices()[0]);
        }
    }

    public static function touchedFileProvider(): \Generator
    {
        yield 'the danger config itself notices' => ['.danger.php', true];
        yield 'an extracted rule class notices' => ['src/Core/DevOps/StaticAnalyze/Danger/Rules/MissingUnitTests.php', true];
        yield 'the runner package manifest notices' => ['vendor-bin/danger-php/composer.json', true];
        yield 'other vendor-bin packages do not notice' => ['vendor-bin/rector/composer.json', false];
        yield 'sibling StaticAnalyze code does not notice' => ['src/Core/DevOps/StaticAnalyze/PHPStan/Rules/RestrictNamespacesRule.php', false];
        yield 'unrelated source files do not notice' => ['src/Core/Framework/Framework.php', false];
    }

    #[TestDox('Emits the notice only once when several watched files are touched')]
    public function testSingleNoticeForMultipleMatches(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile('.danger.php'),
            new StubFile('src/Core/DevOps/StaticAnalyze/Danger/Rules/MissingUnitTests.php'),
            new StubFile('vendor-bin/danger-php/composer.json'),
        ])));

        (new DangerConfigChanged())($context);

        static::assertCount(1, $context->getNotices());
    }
}
