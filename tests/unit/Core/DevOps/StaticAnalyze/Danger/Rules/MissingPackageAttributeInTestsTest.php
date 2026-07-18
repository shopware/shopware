<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingPackageAttributeInTests;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MissingPackageAttributeInTests::class)]
class MissingPackageAttributeInTestsTest extends TestCase
{
    #[TestDox('Fails only for new test classes without the Package attribute')]
    #[DataProvider('addedFileProvider')]
    public function testDetection(string $fileName, string $status, string $content, bool $expectFailure): void
    {
        $context = $this->runRule([new StubFile($fileName, $status, $content)]);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('needs the `#[Package(\'…\')]` attribute', $context->getFailures()[0]);
            static::assertStringContainsString($fileName, $context->getFailures()[0]);
        }
    }

    public static function addedFileProvider(): \Generator
    {
        yield 'new test without Package fails' => [
            'tests/unit/Core/Checkout/Cart/MyCartTest.php',
            File::STATUS_ADDED,
            "class MyCartTest extends TestCase\n{\n}",
            true,
        ];
        yield 'new test with Package passes' => [
            'tests/unit/Core/Checkout/Cart/MyCartTest.php',
            File::STATUS_ADDED,
            "#[Package('checkout')]\nclass MyCartTest extends TestCase\n{\n}",
            false,
        ];
        yield 'modified test without Package passes, only additions are flagged' => [
            'tests/unit/Core/Checkout/Cart/MyCartTest.php',
            File::STATUS_MODIFIED,
            "class MyCartTest extends TestCase\n{\n}",
            false,
        ];
        yield 'Test-suffixed helper without a class declaration passes' => [
            'tests/unit/Core/Checkout/Cart/_fixtures/DataForTest.php',
            File::STATUS_ADDED,
            'return [\'some\' => \'fixture\'];',
            false,
        ];
        yield 'new test outside tests/ is not this rule\'s concern' => [
            'src/Core/Framework/Test/MyFeatureTest.php',
            File::STATUS_ADDED,
            "class MyFeatureTest extends TestCase\n{\n}",
            false,
        ];
    }

    #[TestDox('The suggestion resolves CoversClass through the use statements to the covered class\'s package')]
    public function testSuggestsPackageOfCoveredClass(): void
    {
        $content = <<<'PHP'
            use PHPUnit\Framework\Attributes\CoversClass;
            use Shopware\Core\Checkout\Cart\CartException;

            #[CoversClass(CartException::class)]
            class CartExceptionTest extends TestCase
            {
            }
            PHP;

        $context = $this->runRule([new StubFile('tests/unit/Core/Checkout/Cart/CartExceptionTest.php', File::STATUS_ADDED, $content)]);

        static::assertTrue($context->hasFailures());
        static::assertStringContainsString('probably `#[Package(\'checkout\')]`', $context->getFailures()[0]);
    }

    #[TestDox('The suggestion resolves an inline fully qualified CoversClass target')]
    public function testSuggestsPackageOfInlineFqcnCoveredClass(): void
    {
        $content = <<<'PHP'
            #[CoversClass(\Shopware\Core\Content\LandingPage\LandingPageException::class)]
            class LandingPageExceptionTest extends TestCase
            {
            }
            PHP;

        $context = $this->runRule([new StubFile('tests/unit/Core/Content/LandingPage/LandingPageExceptionTest.php', File::STATUS_ADDED, $content)]);

        static::assertTrue($context->hasFailures());
        static::assertStringContainsString('probably `#[Package(\'discovery\')]`', $context->getFailures()[0]);
    }

    #[TestDox('Without CoversClass the suggestion falls back to the mirrored src directory, walking up missing segments')]
    public function testSuggestsPackageOfMirroredSrcDirectory(): void
    {
        $content = "class CartDoesNotExistDirTest extends TestCase\n{\n}";

        $context = $this->runRule([new StubFile('tests/integration/Core/Checkout/Cart/NotARealSubDir/CartDoesNotExistDirTest.php', File::STATUS_ADDED, $content)]);

        static::assertTrue($context->hasFailures());
        static::assertStringContainsString('probably `#[Package(\'checkout\')]`', $context->getFailures()[0]);
    }

    #[TestDox('A test with an unresolvable location is still flagged, without a suggestion')]
    public function testFlagsWithoutSuggestionWhenNothingResolves(): void
    {
        $content = "class SomethingTest extends TestCase\n{\n}";

        $context = $this->runRule([new StubFile('tests/unit/NoSuchBundle/SomethingTest.php', File::STATUS_ADDED, $content)]);

        static::assertTrue($context->hasFailures());
        static::assertStringNotContainsString('probably', $context->getFailures()[0]);
        static::assertStringContainsString('SomethingTest.php', $context->getFailures()[0]);
    }

    /**
     * @param list<StubFile> $files
     */
    private function runRule(array $files): Context
    {
        $context = new Context(new StubPlatform(new StubPullRequest($files)));

        (new MissingPackageAttributeInTests())($context);

        return $context;
    }
}
