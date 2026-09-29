<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\ComposerVersionConstraints;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ComposerVersionConstraints::class)]
class ComposerVersionConstraintsTest extends TestCase
{
    #[TestDox('Enforces caret by default, tilde for compatibility-sensitive packages, exact pins for pipeline-critical tools')]
    #[DataProvider('constraintProvider')]
    public function testConstraintPolicy(string $package, string $constraint, ?string $expectedFailurePart): void
    {
        $context = $this->createContextWithComposerFile('composer.json', [$package => $constraint]);

        (new ComposerVersionConstraints())($context);

        if ($expectedFailurePart === null) {
            static::assertFalse($context->hasReports());

            return;
        }

        static::assertCount(1, $context->getFailures());
        static::assertStringContainsString($expectedFailurePart, $context->getFailures()[0]);
        static::assertStringContainsString($package, $context->getFailures()[0]);
    }

    public static function constraintProvider(): \Generator
    {
        yield 'regular package with caret passes' => ['monolog/monolog', '^3.5', null];
        yield 'regular package without caret fails' => ['monolog/monolog', '3.5.0', 'caret version range'];
        yield 'symfony package with tilde passes' => ['symfony/console', '~7.4.0', null];
        yield 'symfony package with caret fails' => ['symfony/console', '^7.4', 'tilde version range'];
        yield 'php with tilde passes' => ['php', '~8.2.0', null];
        yield 'doctrine/dbal with caret fails' => ['doctrine/dbal', '^4.0', 'tilde version range'];
        yield 'dompdf with tilde passes' => ['dompdf/dompdf', '~3.1.6', null];
        yield 'dompdf with caret fails' => ['dompdf/dompdf', '^3.1.6', 'tilde version range'];
        yield 'phpstan with exact pin passes' => ['phpstan/phpstan', '2.1.30', null];
        yield 'phpstan with caret fails' => ['phpstan/phpstan', '^2.1', 'pinned to a specific version'];
        yield 'cs-fixer with tilde fails' => ['friendsofphp/php-cs-fixer', '~3.1.0', 'pinned to a specific version'];
        yield 'shopware/conflicts star constraint passes' => ['shopware/conflicts', '*', null];
        yield 'php extension star constraint passes' => ['ext-json', '*', null];
        yield 'polyfills are exempt from the policy' => ['symfony/polyfill-php84', '1.0.0', null];
    }

    #[TestDox('Only require is checked, not require-dev')]
    public function testRequireDevIsNotChecked(): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile('composer.json', File::STATUS_MODIFIED, (string) json_encode([
                'require-dev' => ['monolog/monolog' => '3.5.0'],
            ])),
        ])));

        (new ComposerVersionConstraints())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('Composer files under test directories and removed composer files are exempt')]
    #[DataProvider('exemptFileProvider')]
    public function testExemptComposerFiles(string $fileName, string $status): void
    {
        $context = new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, $status, (string) json_encode([
                'require' => ['monolog/monolog' => '3.5.0'],
            ])),
        ])));

        (new ComposerVersionConstraints())($context);

        static::assertFalse($context->hasReports());
    }

    public static function exemptFileProvider(): \Generator
    {
        yield 'composer.json under tests/ is exempt' => ['tests/acceptance/composer.json', File::STATUS_MODIFIED];
        yield 'composer.json in a Test fixture directory is exempt' => ['src/Core/Framework/Test/Plugin/_fixture/composer.json', File::STATUS_MODIFIED];
        yield 'removed composer.json is exempt' => ['src/Core/composer.json', File::STATUS_REMOVED];
    }

    #[TestDox('Bundle composer files are checked like the root file')]
    public function testBundleComposerFileIsChecked(): void
    {
        $context = $this->createContextWithComposerFile('src/Storefront/composer.json', ['monolog/monolog' => '3.5.0']);

        (new ComposerVersionConstraints())($context);

        static::assertCount(1, $context->getFailures());
        static::assertStringContainsString('src/Storefront/composer.json', $context->getFailures()[0]);
    }

    /**
     * @param array<string, string> $require
     */
    private function createContextWithComposerFile(string $fileName, array $require): Context
    {
        return new Context(new StubPlatform(new StubPullRequest([
            new StubFile($fileName, File::STATUS_MODIFIED, (string) json_encode(['require' => $require])),
        ])));
    }
}
