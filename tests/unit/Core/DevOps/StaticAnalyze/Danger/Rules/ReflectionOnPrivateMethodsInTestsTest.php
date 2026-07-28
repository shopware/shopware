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
    #[TestDox('Fails for reflective invocation added to test files, ignores metadata reads, removals, non-test files and rule fixtures')]
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
        yield 'added reflective invoke in unit test fails' => [
            'tests/unit/Core/Content/Seo/SeoUrlPersisterTest.php',
            File::STATUS_MODIFIED,
            '+        return (bool) $reflection->invoke($this->seoUrlPersister, $existing, $seoUrl, $overwrite);',
            true,
        ];
        yield 'added reflective invokeArgs in new test file fails' => [
            'tests/integration/Core/Checkout/CartTest.php',
            File::STATUS_ADDED,
            '+        $method->invokeArgs($cart, [$context]);',
            true,
        ];
        yield 'added setAccessible in legacy in-src test fails' => [
            'src/Core/Framework/Test/TestCaseBase/DatabaseTransactionBehaviourTest.php',
            File::STATUS_MODIFIED,
            '+        $reflection->setAccessible(true);',
            true,
        ];
        yield 'constructing a reflection object without invoking passes' => [
            'tests/unit/Core/Framework/Mcp/Tool/ToolSearchToolTest.php',
            File::STATUS_MODIFIED,
            '+        $method = new \\ReflectionMethod(ToolSearchTool::class, \'__invoke\');',
            false,
        ];
        yield 'reading metadata off a reflection object passes' => [
            'tests/unit/Core/Framework/Mcp/Tool/ToolSearchToolTest.php',
            File::STATUS_MODIFIED,
            '+        static::assertSame(ToolSearchTool::class, $method->getDeclaringClass()->getName());',
            false,
        ];
        yield 'removed reflective invoke passes' => [
            'tests/unit/Core/Content/Seo/SeoUrlPersisterTest.php',
            File::STATUS_MODIFIED,
            '-        $reflection->invoke($this->seoUrlPersister, $existing);',
            false,
        ];
        yield 'reflective invoke in production code passes' => [
            'src/Core/Content/Seo/SeoUrlPersister.php',
            File::STATUS_MODIFIED,
            '+        $reflection->invoke($this, $existing);',
            false,
        ];
        yield 'rule-test fixture passes' => [
            'tests/devops/Core/DevOps/StaticAnalyse/PHPStan/Rules/data/NoReflectionOnNonPublicMethodsRule/Cases.php',
            File::STATUS_ADDED,
            '+        $reflection->invoke(new Target(), \'privateMethod\');',
            false,
        ];
        yield 'rule-test fixture named like a test under data/ passes' => [
            'tests/devops/Core/DevOps/StaticAnalyse/PHPStan/Rules/data/NoReflectionOnNonPublicMethodsRule/ReflectionCasesTest.php',
            File::STATUS_ADDED,
            '+        $reflection->invoke(new Target(), \'privateMethod\');',
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
