<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\ProcessIsolationInTests;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProcessIsolationInTests::class)]
class ProcessIsolationInTestsTest extends TestCase
{
    #[TestDox('Fails for every added or modified unit or devops test file running in a separate process')]
    #[DataProvider('isolationProvider')]
    public function testDetection(string $fileName, string $status, string $content, ?string $expectedAttribute): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, $status, $content),
        ])));

        (new ProcessIsolationInTests())($context);

        static::assertSame($expectedAttribute !== null, $context->hasFailures());

        if ($expectedAttribute !== null) {
            static::assertStringContainsString($fileName, $context->getFailures()[0]);
            static::assertStringContainsString('`' . $expectedAttribute . '`', $context->getFailures()[0]);
            static::assertStringContainsString('silently loses the guards we rely on', $context->getFailures()[0]);
        }
    }

    public static function isolationProvider(): \Generator
    {
        yield 'new unit test with an isolated method fails' => [
            'fileName' => 'tests/unit/Core/Checkout/CartTest.php',
            'status' => File::STATUS_ADDED,
            'content' => self::testClass('', '#[RunInSeparateProcess]'),
            'expectedAttribute' => 'RunInSeparateProcess',
        ];
        yield 'new unit test with an isolated class fails' => [
            'fileName' => 'tests/unit/Core/Checkout/CartTest.php',
            'status' => File::STATUS_ADDED,
            'content' => self::testClass('#[RunTestsInSeparateProcesses]', ''),
            'expectedAttribute' => 'RunTestsInSeparateProcesses',
        ];
        yield 'new unit test running its class in one separate process fails' => [
            'fileName' => 'tests/unit/Core/Checkout/CartTest.php',
            'status' => File::STATUS_ADDED,
            'content' => self::testClass('#[RunClassInSeparateProcess]', ''),
            'expectedAttribute' => 'RunClassInSeparateProcess',
        ];
        yield 'isolation inside an attribute group fails' => [
            'fileName' => 'tests/unit/Core/Checkout/CartTest.php',
            'status' => File::STATUS_ADDED,
            'content' => self::testClass('', '#[Group(\'slow\'), RunInSeparateProcess]'),
            'expectedAttribute' => 'RunInSeparateProcess',
        ];
        yield 'fully qualified attribute fails' => [
            'fileName' => 'tests/unit/Core/Checkout/CartTest.php',
            'status' => File::STATUS_ADDED,
            'content' => self::testClass('', '#[\PHPUnit\Framework\Attributes\RunInSeparateProcess]'),
            'expectedAttribute' => 'RunInSeparateProcess',
        ];
        yield 'modified unit test still isolated fails, whatever the diff touched' => [
            'fileName' => 'tests/unit/Core/Checkout/CartTest.php',
            'status' => File::STATUS_MODIFIED,
            'content' => self::testClass('', '#[RunInSeparateProcess]'),
            'expectedAttribute' => 'RunInSeparateProcess',
        ];
        yield 'new devops test with an isolated method fails' => [
            'fileName' => 'tests/devops/Core/DevOps/StaticAnalyse/PHPStan/Rules/SomeRuleTest.php',
            'status' => File::STATUS_ADDED,
            'content' => self::testClass('', '#[RunInSeparateProcess]'),
            'expectedAttribute' => 'RunInSeparateProcess',
        ];
        yield 'unit test without isolation passes' => [
            'fileName' => 'tests/unit/Core/Checkout/CartTest.php',
            'status' => File::STATUS_MODIFIED,
            'content' => self::testClass('', ''),
            'expectedAttribute' => null,
        ];
        yield 'attribute name inside a string fixture passes' => [
            'fileName' => 'tests/unit/Core/DevOps/SomeDangerRuleTest.php',
            'status' => File::STATUS_ADDED,
            'content' => self::testClass('', '') . "\n" . '$fixture = "#[RunInSeparateProcess]";',
            'expectedAttribute' => null,
        ];
        yield 'integration test is not checked yet' => [
            'fileName' => 'tests/integration/Core/Framework/Plugin/PluginLifecycleServiceTest.php',
            'status' => File::STATUS_MODIFIED,
            'content' => self::testClass('', '#[RunInSeparateProcess]'),
            'expectedAttribute' => null,
        ];
        yield 'migration test is not checked yet' => [
            'fileName' => 'tests/migration/Core/MigrationForeignDefaultLanguageTest.php',
            'status' => File::STATUS_MODIFIED,
            'content' => self::testClass('#[RunTestsInSeparateProcesses]', ''),
            'expectedAttribute' => null,
        ];
        yield 'removed test is not inspected' => [
            'fileName' => 'tests/unit/Core/Checkout/CartTest.php',
            'status' => File::STATUS_REMOVED,
            'content' => '',
            'expectedAttribute' => null,
        ];
        yield 'rule-test fixture under data/ passes' => [
            'fileName' => 'tests/devops/Core/DevOps/StaticAnalyse/PHPStan/Rules/data/SomeRule/IsolatedTest.php',
            'status' => File::STATUS_ADDED,
            'content' => self::testClass('', '#[RunInSeparateProcess]'),
            'expectedAttribute' => null,
        ];
        yield 'non-test file passes' => [
            'fileName' => 'tests/unit/Core/Checkout/_fixtures/isolated.php',
            'status' => File::STATUS_ADDED,
            'content' => self::testClass('', '#[RunInSeparateProcess]'),
            'expectedAttribute' => null,
        ];
    }

    private static function testClass(string $classAttributes, string $methodAttributes): string
    {
        return "<?php declare(strict_types=1);\n\n"
            . "namespace Shopware\\Tests\\Unit\\Core\\Checkout;\n\n"
            . "use PHPUnit\\Framework\\TestCase;\n\n"
            . $classAttributes . "\n"
            . "class SomeTest extends TestCase\n"
            . "{\n"
            . '    ' . $methodAttributes . "\n"
            . "    public function testSomething(): void\n"
            . "    {\n"
            . "    }\n"
            . "}\n";
    }
}
