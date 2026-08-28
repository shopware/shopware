<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\SingleCoversClassInTests;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SingleCoversClassInTests::class)]
class SingleCoversClassInTestsTest extends TestCase
{
    #[TestDox('Fails only for new test files covering more than one class')]
    #[DataProvider('coversProvider')]
    public function testDetection(string $fileName, string $status, string $content, bool $expectFailure): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, $status, $content),
        ])));

        (new SingleCoversClassInTests())($context);

        static::assertSame($expectFailure, $context->hasFailures());

        if ($expectFailure) {
            static::assertStringContainsString($fileName, $context->getFailures()[0]);
            static::assertStringContainsString('covers exactly one class', $context->getFailures()[0]);
        }
    }

    public static function coversProvider(): \Generator
    {
        yield 'new test covering two classes fails' => [
            'tests/unit/Core/Checkout/CartAndOrderTest.php',
            File::STATUS_ADDED,
            self::testClass("#[CoversClass(Cart::class)]\n#[CoversClass(Order::class)]"),
            true,
        ];
        yield 'new test covering two classes in one attribute group fails' => [
            'tests/unit/Core/Checkout/CartAndOrderTest.php',
            File::STATUS_ADDED,
            self::testClass('#[CoversClass(Cart::class), CoversClass(Order::class)]'),
            true,
        ];
        yield 'new test covering one class passes' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass('#[CoversClass(Cart::class)]'),
            false,
        ];
        yield 'new test without covers passes, presence is the PHPStan rule\'s concern' => [
            'tests/unit/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            self::testClass(''),
            false,
        ];
        yield 'modified test covering two classes passes, only new files are gated' => [
            'tests/unit/Core/Checkout/CartAndOrderTest.php',
            File::STATUS_MODIFIED,
            self::testClass("#[CoversClass(Cart::class)]\n#[CoversClass(Order::class)]"),
            false,
        ];
        yield 'CoversClass strings inside the test body do not count' => [
            'tests/unit/Core/DevOps/SomeDangerRuleTest.php',
            File::STATUS_ADDED,
            self::testClass('#[CoversClass(SomeDangerRule::class)]')
                . "\n// appended fixture-ish content\n" . '$content = "#[CoversClass(A::class)] #[CoversClass(B::class)]";',
            false,
        ];
        yield 'rule-test fixture under data/ passes' => [
            'tests/devops/Core/DevOps/StaticAnalyse/PHPStan/Rules/data/SomeRule/MultiCoversTest.php',
            File::STATUS_ADDED,
            self::testClass("#[CoversClass(A::class)]\n#[CoversClass(B::class)]"),
            false,
        ];
        yield 'non-test file passes' => [
            'tests/unit/Core/Checkout/_fixtures/covers.php',
            File::STATUS_ADDED,
            self::testClass("#[CoversClass(A::class)]\n#[CoversClass(B::class)]"),
            false,
        ];
    }

    private static function testClass(string $attributes): string
    {
        return "<?php declare(strict_types=1);\n\n"
            . "namespace Shopware\\Tests\\Unit\\Core\\Checkout;\n\n"
            . "use PHPUnit\\Framework\\TestCase;\n\n"
            . $attributes . "\n"
            . "class SomeTest extends TestCase\n"
            . "{\n"
            . "}\n";
    }
}
