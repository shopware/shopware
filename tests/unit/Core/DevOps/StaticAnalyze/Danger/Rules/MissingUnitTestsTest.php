<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingUnitTests;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MissingUnitTests::class)]
class MissingUnitTestsTest extends TestCase
{
    private const FIXTURE_CONFIG = __DIR__ . '/_fixtures/phpunit.xml.dist';

    #[TestDox('Fails for a new src class without a matching unit test')]
    public function testFailsForNewClassWithoutTest(): void
    {
        $context = $this->createContext([
            new StubFile('src/Core/Checkout/Cart/CartCalculator.php', File::STATUS_ADDED, "<?php\nclass CartCalculator\n{\n}"),
        ]);

        (new MissingUnitTests(self::FIXTURE_CONFIG))($context);

        static::assertCount(1, $context->getFailures());
        static::assertStringContainsString('Please be kind and add unit tests', $context->getFailures()[0]);
        static::assertStringContainsString('src/Core/Checkout/Cart/CartCalculator.php', $context->getFailures()[0]);
    }

    #[TestDox('Passes when the matching unit test is added in the same pull request')]
    public function testPassesWithMatchingUnitTest(): void
    {
        $context = $this->createContext([
            new StubFile('src/Core/Checkout/Cart/CartCalculator.php', File::STATUS_ADDED, "<?php\nclass CartCalculator\n{\n}"),
            new StubFile(
                'tests/unit/Core/Checkout/Cart/CartCalculatorTest.php',
                File::STATUS_ADDED,
                "<?php\nclass CartCalculatorTest extends TestCase\n{\n}",
            ),
        ]);

        (new MissingUnitTests(self::FIXTURE_CONFIG))($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Skips classes that need no unit test: data holders, non-instantiable types, migrations, DI wiring, coverage-excluded paths')]
    #[DataProvider('exemptSrcFileProvider')]
    public function testExemptSrcFiles(string $fileName, string $content): void
    {
        $context = $this->createContext([new StubFile($fileName, File::STATUS_ADDED, $content)]);

        (new MissingUnitTests(self::FIXTURE_CONFIG))($context);

        static::assertFalse($context->hasReports());
    }

    public static function exemptSrcFileProvider(): \Generator
    {
        yield 'suffix-ignored data holder (Entity)' => [
            'src/Core/Checkout/Cart/CartEntity.php',
            "<?php\nclass CartEntity\n{\n}",
        ];
        yield 'coverage-ignore annotation' => [
            'src/Core/Checkout/Cart/CartCalculator.php',
            "<?php\n/**\n * @codeCoverageIgnore\n */\nclass CartCalculator\n{\n}",
        ];
        yield 'abstract class' => [
            'src/Core/Checkout/Cart/AbstractCartCalculator.php',
            "<?php\nabstract class AbstractCartCalculator\n{\n}",
        ];
        yield 'interface' => [
            'src/Core/Checkout/Cart/CartCalculatorInterface.php',
            "<?php\ninterface CartCalculatorInterface\n{\n}",
        ];
        yield 'trait' => [
            'src/Core/Checkout/Cart/CartCalculatorTrait.php',
            "<?php\ntrait CartCalculatorTrait\n{\n}",
        ];
        yield 'migration class' => [
            'src/Core/Migration/V6_7/Migration1752000000AddFoo.php',
            "<?php\nclass Migration1752000000AddFoo\n{\n}",
        ];
        yield 'DI service-wiring file' => [
            'src/Core/Framework/DependencyInjection/services.php',
            "<?php\nreturn static function (ContainerConfigurator \$configurator): void {\n};",
        ];
        yield 'file inside a coverage-excluded directory' => [
            'src/Core/DevOps/StaticAnalyze/SomeService.php',
            "<?php\nclass SomeService\n{\n}",
        ];
        yield 'file matching a coverage-excluded directory with suffix' => [
            'src/Core/Content/Product/ProductFeedProvider.php',
            "<?php\nclass ProductFeedProvider\n{\n}",
        ];
        yield 'coverage-excluded single file' => [
            'src/Core/Framework/ExcludedService.php',
            "<?php\nclass ExcludedService\n{\n}",
        ];
    }

    #[TestDox('A test class extending an unknown base class does not count as coverage')]
    public function testUnknownTestBaseClassDoesNotCount(): void
    {
        $context = $this->createContext([
            new StubFile('src/Core/Checkout/Cart/CartCalculator.php', File::STATUS_ADDED, "<?php\nclass CartCalculator\n{\n}"),
            new StubFile(
                'tests/unit/Core/Checkout/Cart/CartCalculatorTest.php',
                File::STATUS_ADDED,
                "<?php\nclass CartCalculatorTest extends SomeExoticTestCase\n{\n}",
            ),
        ]);

        (new MissingUnitTests(self::FIXTURE_CONFIG))($context);

        static::assertCount(1, $context->getFailures());
    }

    #[TestDox('Warns when the phpunit config cannot be loaded and keeps checking without excludes')]
    public function testWarnsForUnloadablePhpunitConfig(): void
    {
        $context = $this->createContext([
            new StubFile('src/Core/Checkout/Cart/CartEntity.php', File::STATUS_ADDED, "<?php\nclass CartEntity\n{\n}"),
        ]);

        // DOMDocument::load() emits an E_WARNING for the missing file; swallow it locally
        set_error_handler(static fn (): bool => true, \E_WARNING);
        try {
            (new MissingUnitTests(__DIR__ . '/_fixtures/does-not-exist.xml'))($context);
        } finally {
            restore_error_handler();
        }

        static::assertCount(1, $context->getWarnings());
        static::assertStringContainsString('Was not able to load phpunit config file', $context->getWarnings()[0]);
        // the suffix exemption still applies without a loadable config
        static::assertFalse($context->hasFailures());
    }

    #[TestDox('A phpunit.xml.dist changed in the pull request replaces the checked-in one')]
    public function testPhpunitConfigFromPullRequestWins(): void
    {
        $prConfig = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <phpunit>
                <source>
                    <exclude>
                        <directory>src/Core/Checkout</directory>
                    </exclude>
                </source>
            </phpunit>
            XML;

        $context = $this->createContext([
            new StubFile('src/Core/Checkout/Cart/CartCalculator.php', File::STATUS_ADDED, "<?php\nclass CartCalculator\n{\n}"),
            new StubFile('phpunit.xml.dist', File::STATUS_MODIFIED, $prConfig),
        ]);

        (new MissingUnitTests(self::FIXTURE_CONFIG))($context);

        static::assertFalse($context->hasFailures());
    }

    /**
     * @param list<File> $files
     */
    private function createContext(array $files): Context
    {
        return new Context(new StubPlatform(new StubPullRequest($files)));
    }
}
