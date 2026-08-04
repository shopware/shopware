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
    #[TestDox('Fails on proven non-public Shopware targets, warns on unresolvable targets, passes third-party and public ones')]
    #[DataProvider('patchProvider')]
    public function testReflectionDetection(
        string $fileName,
        string $status,
        string $patch,
        string $content,
        bool $expectFailure,
        bool $expectWarning,
    ): void {
        $context = $this->runRule([new StubFile($fileName, $status, $content, $patch)]);

        static::assertSame($expectFailure, $context->hasFailures());
        static::assertSame($expectWarning, $context->hasWarnings());

        if ($expectFailure) {
            static::assertStringContainsString($fileName, $context->getFailures()[0]);
        }

        if ($expectWarning) {
            static::assertStringContainsString('reflection', $context->getWarnings()[0]);
            static::assertStringContainsString($fileName, $context->getWarnings()[0]);
        }
    }

    public static function patchProvider(): \Generator
    {
        $privateConstruction = '        $method = new \ReflectionMethod(ServiceWithHiddenMethods::class, \'hiddenCalculation\');';
        $invoke = '        $method->invoke($service);';

        yield 'invoking a private method of a Shopware class fails' => [
            'tests/unit/Core/Checkout/SomeServiceTest.php',
            File::STATUS_MODIFIED,
            self::added($privateConstruction, $invoke),
            self::testFileContent($privateConstruction . "\n" . $invoke),
            true,
            false,
        ];
        yield 'invoking a protected method of a Shopware class fails' => [
            'tests/unit/Core/Checkout/SomeServiceTest.php',
            File::STATUS_ADDED,
            self::added(
                '        $method = new \ReflectionMethod(ServiceWithHiddenMethods::class, \'guardedStep\');',
                '        $method->invokeArgs($service, []);',
            ),
            self::testFileContent(
                "        \$method = new \\ReflectionMethod(ServiceWithHiddenMethods::class, 'guardedStep');\n"
                . '        $method->invokeArgs($service, []);'
            ),
            true,
            false,
        ];
        yield 'invoking a public method of a Shopware class passes' => [
            'tests/unit/Core/Checkout/SomeServiceTest.php',
            File::STATUS_MODIFIED,
            self::added(
                '        $method = new \ReflectionMethod(ServiceWithHiddenMethods::class, \'publicEntryPoint\');',
                $invoke,
            ),
            self::testFileContent(
                "        \$method = new \\ReflectionMethod(ServiceWithHiddenMethods::class, 'publicEntryPoint');\n"
                . $invoke
            ),
            false,
            false,
        ];
        yield 'invoking a third-party method passes' => [
            'tests/unit/Core/Checkout/SomeServiceTest.php',
            File::STATUS_MODIFIED,
            self::added(
                '        $method = new \ReflectionMethod(Request::class, \'initialize\');',
                '        $method->invoke($request);',
            ),
            self::testFileContent(
                "        \$method = new \\ReflectionMethod(Request::class, 'initialize');\n"
                . '        $method->invoke($request);'
            ),
            false,
            false,
        ];
        yield 'setAccessible on a proven private method fails' => [
            'tests/unit/Core/Checkout/SomeServiceTest.php',
            File::STATUS_MODIFIED,
            self::added($privateConstruction, '        $method->setAccessible(true);'),
            self::testFileContent($privateConstruction . "\n" . '        $method->setAccessible(true);'),
            true,
            false,
        ];
        yield 'an unresolvable reflection target warns' => [
            'tests/unit/Core/Checkout/SomeServiceTest.php',
            File::STATUS_MODIFIED,
            self::added(
                '        $method = new \ReflectionMethod($this->subject, \'doTheThing\');',
                $invoke,
            ),
            self::testFileContent(
                "        \$method = new \\ReflectionMethod(\$this->subject, 'doTheThing');\n"
                . $invoke
            ),
            false,
            true,
        ];
        yield 'a Shopware target class missing from the checkout warns' => [
            'tests/unit/Core/Checkout/SomeServiceTest.php',
            File::STATUS_MODIFIED,
            self::added(
                '        $method = new \ReflectionMethod(\Shopware\Core\Checkout\Missing\UnknownService::class, \'hidden\');',
                $invoke,
            ),
            self::testFileContent(
                "        \$method = new \\ReflectionMethod(\\Shopware\\Core\\Checkout\\Missing\\UnknownService::class, 'hidden');\n"
                . $invoke
            ),
            false,
            true,
        ];
        yield 'a construction outside the diff still resolves through the file content' => [
            'tests/unit/Core/Checkout/SomeServiceTest.php',
            File::STATUS_MODIFIED,
            self::added($invoke),
            self::testFileContent(
                "        \$method = (new \\ReflectionClass(ServiceWithHiddenMethods::class))->getMethod('hiddenCalculation');\n"
                . $invoke
            ),
            true,
            false,
        ];
        yield 'getMethod on a tracked ReflectionClass variable resolves' => [
            'tests/unit/Core/Checkout/SomeServiceTest.php',
            File::STATUS_MODIFIED,
            self::added(
                '        $ref = new \ReflectionClass(ServiceWithHiddenMethods::class);',
                '        $method = $ref->getMethod(\'hiddenCalculation\');',
                $invoke,
            ),
            self::testFileContent(
                "        \$ref = new \\ReflectionClass(ServiceWithHiddenMethods::class);\n"
                . "        \$method = \$ref->getMethod('hiddenCalculation');\n"
                . $invoke
            ),
            true,
            false,
        ];
        yield 'legacy in-src test with unresolvable reflection warns' => [
            'src/Core/Framework/Test/TestCaseBase/DatabaseTransactionBehaviourTest.php',
            File::STATUS_MODIFIED,
            self::added('        $reflection->setAccessible(true);'),
            self::testFileContent('        $reflection->setAccessible(true);'),
            false,
            true,
        ];
        yield 'constructing a reflection object without invoking passes' => [
            'tests/unit/Core/Framework/Mcp/Tool/ToolSearchToolTest.php',
            File::STATUS_MODIFIED,
            self::added('        $method = new \ReflectionMethod(ToolSearchTool::class, \'__invoke\');'),
            '',
            false,
            false,
        ];
        yield 'reading metadata off a reflection object passes' => [
            'tests/unit/Core/Framework/Mcp/Tool/ToolSearchToolTest.php',
            File::STATUS_MODIFIED,
            self::added('        static::assertSame(ToolSearchTool::class, $method->getDeclaringClass()->getName());'),
            '',
            false,
            false,
        ];
        yield 'removed reflective invoke passes' => [
            'tests/unit/Core/Content/Seo/SeoUrlPersisterTest.php',
            File::STATUS_MODIFIED,
            '-        $reflection->invoke($this->seoUrlPersister, $existing);',
            '',
            false,
            false,
        ];
        yield 'reflective invoke in production code passes' => [
            'src/Core/Content/Seo/SeoUrlPersister.php',
            File::STATUS_MODIFIED,
            self::added('        $reflection->invoke($this, $existing);'),
            '',
            false,
            false,
        ];
        yield 'rule-test fixture passes' => [
            'tests/devops/Core/DevOps/StaticAnalyse/PHPStan/Rules/data/NoReflectionOnNonPublicMethodsRule/Cases.php',
            File::STATUS_ADDED,
            self::added('        $reflection->invoke(new Target(), \'privateMethod\');'),
            '',
            false,
            false,
        ];
        yield 'rule-test fixture named like a test under data/ passes' => [
            'tests/devops/Core/DevOps/StaticAnalyse/PHPStan/Rules/data/NoReflectionOnNonPublicMethodsRule/ReflectionCasesTest.php',
            File::STATUS_ADDED,
            self::added('        $reflection->invoke(new Target(), \'privateMethod\');'),
            '',
            false,
            false,
        ];
        yield 'http request getMethod passes' => [
            'tests/unit/Core/Framework/Api/RequestTest.php',
            File::STATUS_MODIFIED,
            self::added('        static::assertSame(\'GET\', $request->getMethod());'),
            '',
            false,
            false,
        ];
    }

    #[TestDox('The failure names the reflected method and its visibility')]
    public function testFailureNamesMethodAndVisibility(): void
    {
        $construction = '        $method = new \ReflectionMethod(ServiceWithHiddenMethods::class, \'hiddenCalculation\');';
        $invoke = '        $method->invoke($service);';

        $context = $this->runRule([new StubFile(
            'tests/unit/Core/Checkout/SomeServiceTest.php',
            File::STATUS_MODIFIED,
            self::testFileContent($construction . "\n" . $invoke),
            self::added($construction, $invoke),
        )]);

        static::assertTrue($context->hasFailures());
        static::assertStringContainsString(
            'Shopware\Core\Checkout\Fixture\ServiceWithHiddenMethods::hiddenCalculation()',
            $context->getFailures()[0]
        );
        static::assertStringContainsString('is private', $context->getFailures()[0]);
    }

    #[TestDox('The pull request\'s version of the target class wins over the checkout')]
    public function testPullRequestVersionOfTargetClassWins(): void
    {
        $construction = '        $method = new \ReflectionMethod(ServiceWithHiddenMethods::class, \'hiddenCalculation\');';
        $invoke = '        $method->invoke($service);';

        // the PR makes the method public, the fixture checkout still has it private
        $classSource = "<?php declare(strict_types=1);\n\n"
            . "namespace Shopware\\Core\\Checkout\\Fixture;\n\n"
            . "class ServiceWithHiddenMethods\n"
            . "{\n"
            . "    public function hiddenCalculation(): void\n"
            . "    {\n"
            . "    }\n"
            . "}\n";

        $context = $this->runRule([
            new StubFile(
                'tests/unit/Core/Checkout/SomeServiceTest.php',
                File::STATUS_MODIFIED,
                self::testFileContent($construction . "\n" . $invoke),
                self::added($construction, $invoke),
            ),
            new StubFile(
                'src/Core/Checkout/Fixture/ServiceWithHiddenMethods.php',
                File::STATUS_MODIFIED,
                $classSource,
            ),
        ]);

        static::assertFalse($context->hasFailures());
        static::assertFalse($context->hasWarnings());
    }

    #[TestDox('A class added by the pull request itself is resolved from the pull request')]
    public function testClassAddedInSamePullRequestIsResolved(): void
    {
        $construction = '        $method = new \ReflectionMethod(\Shopware\Core\Checkout\Fixture\BrandNewService::class, \'freshSecret\');';
        $invoke = '        $method->invoke($service);';

        $classSource = "<?php declare(strict_types=1);\n\n"
            . "namespace Shopware\\Core\\Checkout\\Fixture;\n\n"
            . "class BrandNewService\n"
            . "{\n"
            . "    private function freshSecret(): void\n"
            . "    {\n"
            . "    }\n"
            . "}\n";

        $context = $this->runRule([
            new StubFile(
                'tests/unit/Core/Checkout/BrandNewServiceTest.php',
                File::STATUS_ADDED,
                self::testFileContent($construction . "\n" . $invoke),
                self::added($construction, $invoke),
            ),
            new StubFile(
                'src/Core/Checkout/Fixture/BrandNewService.php',
                File::STATUS_ADDED,
                $classSource,
            ),
        ]);

        static::assertTrue($context->hasFailures());
        static::assertStringContainsString('BrandNewService::freshSecret()', $context->getFailures()[0]);
        static::assertFalse($context->hasWarnings());
    }

    /**
     * @param list<StubFile> $files
     */
    private function runRule(array $files): Context
    {
        $context = new Context(new StubPlatform(new StubPullRequest($files)));

        (new ReflectionOnPrivateMethodsInTests(__DIR__ . '/_fixtures'))($context);

        return $context;
    }

    private static function testFileContent(string $body): string
    {
        return "<?php declare(strict_types=1);\n\n"
            . "namespace Shopware\\Tests\\Unit\\Core\\Checkout;\n\n"
            . "use PHPUnit\\Framework\\TestCase;\n"
            . "use Shopware\\Core\\Checkout\\Fixture\\ServiceWithHiddenMethods;\n"
            . "use Symfony\\Component\\HttpFoundation\\Request;\n\n"
            . "class SomeServiceTest extends TestCase\n"
            . "{\n"
            . "    public function testSubject(): void\n"
            . "    {\n"
            . $body . "\n"
            . "    }\n"
            . "}\n";
    }

    private static function added(string ...$lines): string
    {
        return implode("\n", array_map(static fn (string $line): string => '+' . $line, $lines));
    }
}
