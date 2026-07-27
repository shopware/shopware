<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\ReflectionOnPrivateMethodsInTests;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReflectionOnPrivateMethodsInTests::class)]
class ReflectionOnPrivateMethodsInTestsTest extends TestCase
{
    #[TestDox('Fails for reflection on methods added to test files, ignores removals, non-test files and rule fixtures')]
    #[DataProvider('patchProvider')]
    public function testReflectionDetection(string $fileName, string $status, string $patch, bool $expectFailure): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, $status, '', $patch),
        ])));

        (new ReflectionOnPrivateMethodsInTests())($context);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('reflection', $context->getFailures()[0]);
            static::assertStringContainsString($fileName, $context->getFailures()[0]);
        }
    }

    public static function patchProvider(): \Generator
    {
        yield 'added ReflectionMethod in unit test fails' => [
            'tests/unit/Core/Content/Seo/SeoUrlPersisterTest.php',
            File::STATUS_MODIFIED,
            '+        $reflection = new \\ReflectionMethod(SeoUrlPersister::class, \'skipUpdate\');',
            true,
        ];
        yield 'added ReflectionClass getMethod in new test file fails' => [
            'tests/integration/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            '+        $method = (new \\ReflectionClass(Cart::class))->getMethod(\'refresh\');',
            true,
        ];
        yield 'added setAccessible in legacy in-src test fails' => [
            'src/Core/Framework/Test/TestCaseBase/DatabaseTransactionBehaviourTest.php',
            File::STATUS_MODIFIED,
            '+        $reflection->setAccessible(true);',
            true,
        ];
        yield 'removed reflection line passes' => [
            'tests/unit/Core/Content/Seo/SeoUrlPersisterTest.php',
            File::STATUS_MODIFIED,
            '-        $reflection = new \\ReflectionMethod(SeoUrlPersister::class, \'skipUpdate\');',
            false,
        ];
        yield 'reflection in production code passes' => [
            'src/Core/Content/Seo/SeoUrlPersister.php',
            File::STATUS_MODIFIED,
            '+        $reflection = new \\ReflectionMethod(SeoUrlPersister::class, \'skipUpdate\');',
            false,
        ];
        yield 'rule-test fixture passes' => [
            'tests/devops/Core/DevOps/StaticAnalyse/PHPStan/Rules/data/NoReflectionOnNonPublicMethodsRule/Cases.php',
            File::STATUS_ADDED,
            '+        $reflection = new \\ReflectionMethod(Target::class, \'privateMethod\');',
            false,
        ];
        yield 'rule-test fixture named like a test under data/ passes' => [
            'tests/devops/Core/DevOps/StaticAnalyse/PHPStan/Rules/data/NoReflectionOnNonPublicMethodsRule/ReflectionCasesTest.php',
            File::STATUS_ADDED,
            '+        $reflection = new \\ReflectionMethod(Target::class, \'privateMethod\');',
            false,
        ];
        yield 'http request getMethod passes' => [
            'tests/unit/Core/Framework/Api/RequestTest.php',
            File::STATUS_MODIFIED,
            '+        static::assertSame(\'GET\', $request->getMethod());',
            false,
        ];
    }
}
