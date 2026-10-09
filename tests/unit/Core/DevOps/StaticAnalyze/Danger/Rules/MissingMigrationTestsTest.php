<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingMigrationTests;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MissingMigrationTests::class)]
class MissingMigrationTestsTest extends TestCase
{
    /**
     * @param list<string> $addedFiles
     */
    #[TestDox('Requires a tests/migration test for every bundle that adds a migration')]
    #[DataProvider('migrationFilesProvider')]
    public function testMigrationTestRequirement(array $addedFiles, int $expectedFailures): void
    {
        $files = array_map(static fn (string $name): StubFile => new StubFile($name, File::STATUS_ADDED), $addedFiles);
        $context = new Context(new StubPlatform(new StubPullRequest($files)));

        (new MissingMigrationTests())($context);

        static::assertCount($expectedFailures, $context->getFailures());
        foreach ($context->getFailures() as $failure) {
            static::assertSame('Please add tests for your new Migration file', $failure);
        }
    }

    public static function migrationFilesProvider(): \Generator
    {
        yield 'core migration without test fails' => [
            ['src/Core/Migration/V6_7/Migration1752000000AddFoo.php'],
            1,
        ];
        yield 'core migration with test passes' => [
            [
                'src/Core/Migration/V6_7/Migration1752000000AddFoo.php',
                'tests/migration/Core/V6_7/Migration1752000000AddFooTest.php',
            ],
            0,
        ];
        yield 'each bundle is checked independently: untested migrations in two bundles fail twice' => [
            [
                'src/Core/Migration/V6_7/Migration1752000000AddFoo.php',
                'src/Storefront/Migration/V6_7/Migration1752000001AddBar.php',
            ],
            2,
        ];
        yield 'a test in one bundle does not cover a migration in another' => [
            [
                'src/Storefront/Migration/V6_7/Migration1752000001AddBar.php',
                'tests/migration/Core/V6_7/Migration1752000001AddBarTest.php',
            ],
            1,
        ];
        yield 'modified migration files are not flagged' => [
            [],
            0,
        ];
    }

    #[TestDox('A moved migration test satisfies the rule for the migration it accompanies')]
    public function testRenamedMigrationTestCountsAsCoverage(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile('src/Core/Migration/V6_8/Migration1752000000AddFoo.php', File::STATUS_ADDED),
            new StubFile('tests/migration/Core/V6_8/Migration1752000000AddFooTest.php', File::STATUS_RENAMED),
        ])));

        (new MissingMigrationTests())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('A deleted migration test does not satisfy the rule')]
    public function testRemovedMigrationTestDoesNotCount(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile('src/Core/Migration/V6_8/Migration1752000000AddFoo.php', File::STATUS_ADDED),
            new StubFile('tests/migration/Core/V6_8/Migration1752000000AddFooTest.php', File::STATUS_REMOVED),
        ])));

        (new MissingMigrationTests())($context);

        static::assertCount(1, $context->getFailures());
    }

    #[TestDox('Only added migration files trigger the rule, modifications do not')]
    public function testModifiedMigrationIsIgnored(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile('src/Core/Migration/V6_7/Migration1752000000AddFoo.php', File::STATUS_MODIFIED),
        ])));

        (new MissingMigrationTests())($context);

        static::assertFalse($context->hasReports());
    }
}
