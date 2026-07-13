<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingReleaseInfo;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[CoversClass(MissingReleaseInfo::class)]
class MissingReleaseInfoTest extends TestCase
{
    #[TestDox('Warns when the pull request does not touch the release info file')]
    public function testWarnsWithoutReleaseInfo(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([new StubFile('src/Core/Framework/Framework.php')])));

        (new MissingReleaseInfo())($context);

        static::assertCount(1, $context->getWarnings());
        static::assertStringContainsString('doesn\'t contain any release info', $context->getWarnings()[0]);
    }

    #[TestDox('Stays silent when the release info file is part of the pull request')]
    public function testSilentWithReleaseInfo(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile('src/Core/Framework/Framework.php'),
            new StubFile('RELEASE_INFO-6.7.md'),
        ])));

        (new MissingReleaseInfo())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('The checked release info file name is configurable')]
    public function testConfigurableReleaseInfoFile(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([new StubFile('RELEASE_INFO-6.8.md')])));

        (new MissingReleaseInfo('RELEASE_INFO-6.8.md'))($context);

        static::assertFalse($context->hasReports());
    }

    /**
     * @param list<string> $touchedFiles
     */
    #[TestDox('Test-only pull requests are never relevant for external developers and skip the warning')]
    #[DataProvider('suiteOnlyFilesProvider')]
    public function testTestOnlyPullRequests(array $touchedFiles, bool $expectWarning): void
    {
        $files = array_map(static fn (string $name): StubFile => new StubFile($name), $touchedFiles);
        $context = new Context(new StubPlatform(new StubPullRequest($files)));

        (new MissingReleaseInfo())($context);

        static::assertSame($expectWarning, $context->hasWarnings());
    }

    public static function suiteOnlyFilesProvider(): \Generator
    {
        yield 'unit-test-only change skips the warning' => [
            ['tests/unit/Core/Checkout/Cart/CartCalculatorTest.php'],
            false,
        ];
        yield 'integration-test-only change skips the warning' => [
            ['tests/integration/Core/Framework/Api/SyncControllerTest.php'],
            false,
        ];
        yield 'devops-test-only change skips the warning' => [
            ['tests/devops/Core/DevOps/StaticAnalyse/PHPStan/Rules/SomeRuleTest.php'],
            false,
        ];
        yield 'a mix of the three test suites skips the warning' => [
            [
                'tests/unit/Core/Checkout/Cart/CartCalculatorTest.php',
                'tests/integration/Core/Framework/Api/SyncControllerTest.php',
                'tests/devops/Core/DevOps/StaticAnalyse/PHPStan/Rules/SomeRuleTest.php',
            ],
            false,
        ];
        yield 'tests mixed with src changes still warn' => [
            [
                'tests/unit/Core/Checkout/Cart/CartCalculatorTest.php',
                'src/Core/Checkout/Cart/CartCalculator.php',
            ],
            true,
        ];
        yield 'migration tests are not exempt, migrations can be externally relevant' => [
            ['tests/migration/Core/V6_7/Migration1752000000AddFooTest.php'],
            true,
        ];
    }
}
