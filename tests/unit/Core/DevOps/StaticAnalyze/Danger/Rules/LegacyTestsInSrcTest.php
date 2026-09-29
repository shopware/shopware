<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\LegacyTestsInSrc;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LegacyTestsInSrc::class)]
class LegacyTestsInSrcTest extends TestCase
{
    #[TestDox('Fails only for new TestCase classes added under src/')]
    #[DataProvider('addedFileProvider')]
    public function testLegacyTestDetection(string $fileName, string $status, string $content, bool $expectFailure): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, $status, $content),
        ])));

        (new LegacyTestsInSrc())($context);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('Don\'t add new testcases in the `/src` folder', $context->getFailures()[0]);
            static::assertStringContainsString($fileName, $context->getFailures()[0]);
        }
    }

    public static function addedFileProvider(): \Generator
    {
        yield 'new TestCase under src fails' => [
            'src/Core/Framework/Test/MyFeatureTest.php',
            File::STATUS_ADDED,
            "class MyFeatureTest extends TestCase\n{\n}",
            true,
        ];
        yield 'new unit test under tests/unit passes' => [
            'tests/unit/Core/Framework/MyFeatureTest.php',
            File::STATUS_ADDED,
            "class MyFeatureTest extends TestCase\n{\n}",
            false,
        ];
        yield 'Test-suffixed helper under src that is no TestCase passes' => [
            'src/Core/Framework/Test/IdsCollectionTest.php',
            File::STATUS_ADDED,
            "class IdsCollectionTest\n{\n}",
            false,
        ];
        yield 'modified legacy test under src passes, only additions are flagged' => [
            'src/Core/Framework/Test/MyFeatureTest.php',
            File::STATUS_MODIFIED,
            "class MyFeatureTest extends TestCase\n{\n}",
            false,
        ];
    }
}
