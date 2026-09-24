<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\TraitUsageInNewUnitTests;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TraitUsageInNewUnitTests::class)]
class TraitUsageInNewUnitTestsTest extends TestCase
{
    #[TestDox('Fails only for new unit test classes composing a trait that is not a lifecycle behaviour')]
    #[DataProvider('traitProvider')]
    public function testDetection(string $fileName, string $status, string $content, ?string $expectedTrait): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, $status, $content),
        ])));

        (new TraitUsageInNewUnitTests())($context);

        static::assertSame($expectedTrait !== null, $context->hasFailures());
        if ($expectedTrait !== null) {
            static::assertStringContainsString($fileName, $context->getFailures()[0]);
            static::assertStringContainsString($expectedTrait, $context->getFailures()[0]);
        }
    }

    public static function traitProvider(): \Generator
    {
        yield 'new unit test with a fixture trait fails' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass("use Shopware\\Tests\\Unit\\Core\\Checkout\\Helper\\CartHelperTrait;\n", "    use CartHelperTrait;\n"),
            'Shopware\Tests\Unit\Core\Checkout\Helper\CartHelperTrait',
        ];

        yield 'new unit test with an aliased trait fails under its real name' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass("use Shopware\\Core\\Framework\\Test\\TestCaseBase\\KernelTestBehaviour as Kernel;\n", "    use Kernel;\n"),
            'Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour',
        ];

        yield 'new unit test with a trait from its own namespace fails' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass('', "    use CartHelperTrait;\n"),
            'Shopware\Tests\Unit\Core\Checkout\CartHelperTrait',
        ];

        yield 'new unit test listing two traits reports the one that is not allowed' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass("use Symfony\\Component\\Clock\\Test\\ClockSensitiveTrait;\nuse Shopware\\Tests\\Unit\\Core\\Checkout\\Helper\\CartHelperTrait;\n", "    use ClockSensitiveTrait, CartHelperTrait;\n"),
            'Shopware\Tests\Unit\Core\Checkout\Helper\CartHelperTrait',
        ];

        yield 'new unit test with the event dispatcher behaviour fails, a unit test owns its dispatcher' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass("use Shopware\\Core\\Framework\\Test\\TestCaseBase\\EventDispatcherBehaviour;\n", "    use EventDispatcherBehaviour;\n"),
            'Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour',
        ];

        yield 'new unit test with the clock behaviour passes' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass("use Symfony\\Component\\Clock\\Test\\ClockSensitiveTrait;\n", "    use ClockSensitiveTrait;\n"),
            null,
        ];

        yield 'new unit test with the environment behaviour fails, TestEnvironment replaces it' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass("use Shopware\\Core\\Framework\\Test\\TestCaseBase\\EnvTestBehaviour;\n", "    use EnvTestBehaviour;\n"),
            'Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour',
        ];

        yield 'new unit test without traits passes' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass('', ''),
            null,
        ];

        yield 'closure use clauses are not trait uses' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass('', "    public function testOne(): void\n    {\n        \$value = 1;\n        \$closure = static function () use (\$value): int {\n            return \$value;\n        };\n        static::assertSame(1, \$closure());\n    }\n"),
            null,
        ];

        yield 'a stub class after the test class may compose a production trait' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass("use Shopware\\Core\\Framework\\Struct\\CloneTrait;\n", '') . "\nclass CartStub\n{\n    use CloneTrait;\n}\n",
            null,
        ];

        yield 'modified unit test with a fixture trait passes, only new files are gated' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_MODIFIED,
            self::testClass("use Shopware\\Tests\\Unit\\Core\\Checkout\\Helper\\CartHelperTrait;\n", "    use CartHelperTrait;\n"),
            null,
        ];

        yield 'new integration test with a trait passes, the rule is about the unit suite' => [
            'tests/integration/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass("use Shopware\\Core\\Framework\\Test\\TestCaseBase\\IntegrationTestBehaviour;\n", "    use IntegrationTestBehaviour;\n"),
            null,
        ];

        yield 'rule fixtures under a data folder are skipped' => [
            'tests/unit/Core/DevOps/data/SomeRule/CartTest.php',
            File::STATUS_ADDED,
            self::testClass("use Shopware\\Tests\\Unit\\Core\\Checkout\\Helper\\CartHelperTrait;\n", "    use CartHelperTrait;\n"),
            null,
        ];
    }

    private static function testClass(string $imports, string $body): string
    {
        return "<?php declare(strict_types=1);\n\nnamespace Shopware\\Tests\\Unit\\Core\\Checkout;\n\nuse PHPUnit\\Framework\\TestCase;\n"
            . $imports
            . "\nclass CartTest extends TestCase\n{\n" . $body . "}\n";
    }
}
