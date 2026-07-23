<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\InlineRuleInDangerConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InlineRuleInDangerConfig::class)]
class InlineRuleInDangerConfigTest extends TestCase
{
    #[TestDox('Fails for inline rules added to .danger.php, allows class registrations, removals and other files')]
    #[DataProvider('patchProvider')]
    public function testInlineRuleDetection(string $fileName, string $patch, bool $expectFailure): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, File::STATUS_MODIFIED, '', $patch),
        ])));

        (new InlineRuleInDangerConfig())($context);

        static::assertSame($expectFailure, $context->hasFailures());
        if ($expectFailure) {
            static::assertStringContainsString('src/Core/DevOps/StaticAnalyze/Danger/Rules', $context->getFailures()[0]);
        }
    }

    public static function patchProvider(): \Generator
    {
        yield 'added closure rule fails' => [
            '.danger.php',
            "+    ->useRule(function (Context \$context): void {\n+        \$context->notice('x');\n+    })",
            true,
        ];
        yield 'added static closure rule fails' => [
            '.danger.php',
            '+    ->useRule(static function (Context $context): void {})',
            true,
        ];
        yield 'added arrow function rule fails' => [
            '.danger.php',
            '+    ->useRule(fn (Context $context) => $context->notice(\'x\'))',
            true,
        ];
        yield 'added anonymous class rule fails' => [
            '.danger.php',
            '+    ->useRule(new class {',
            true,
        ];
        yield 'added variable rule fails' => [
            '.danger.php',
            '+    ->useRule($myRule)',
            true,
        ];
        yield 'closure on its own added line fails, useRule split across lines' => [
            '.danger.php',
            "+    ->useRule(\n+        function (Context \$context): void {}\n+    )",
            true,
        ];
        yield 'added class registration passes' => [
            '.danger.php',
            '+    ->useRule(new MissingReleaseInfo())',
            false,
        ];
        yield 'added fully qualified class registration passes' => [
            '.danger.php',
            '+    ->useRule(new \Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\MissingReleaseInfo())',
            false,
        ];
        yield 'class registration split across lines passes' => [
            '.danger.php',
            "+    ->useRule(\n+        new MissingReleaseInfo()\n+    )",
            false,
        ];
        yield 'removed inline rule passes' => [
            '.danger.php',
            '-    ->useRule(function (Context $context): void {})',
            false,
        ];
        yield 'closure in a rule class passes, only the config is checked' => [
            'src/Core/DevOps/StaticAnalyze/Danger/Rules/MissingReleaseInfo.php',
            '+        $files->filter(fn (File $file): bool => true);',
            false,
        ];
    }
}
