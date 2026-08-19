<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\Danger\Rules\IgnoredPhpstanErrorsInTouchedFiles;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubFile;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPlatform;
use Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\Danger\Stub\StubPullRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IgnoredPhpstanErrorsInTouchedFiles::class)]
class IgnoredPhpstanErrorsInTouchedFilesTest extends TestCase
{
    #[TestDox('Fails when a touched file still has baselined PHPStan errors')]
    public function testFailsForTouchedFileWithBaselinedErrors(): void
    {
        $context = $this->createContext(
            ['src/Core/Framework/Api/ApiController.php', 'src/Core/Framework/Api/Clean.php'],
            "'path' => __DIR__ . 'src/Core/Framework/Api/ApiController.php'\n",
        );

        (new IgnoredPhpstanErrorsInTouchedFiles())($context);

        static::assertCount(1, $context->getFailures());
        static::assertStringContainsString('contain ignored PHPStan errors', $context->getFailures()[0]);
        static::assertStringContainsString('src/Core/Framework/Api/ApiController.php', $context->getFailures()[0]);
        static::assertStringNotContainsString('Clean.php', $context->getFailures()[0]);
    }

    #[TestDox('Fails when a touched file still has baselined PHPStan errors ignoring any path prefix')]
    public function testFailsForTouchedFileWithBaselinedErrorsWithOnlyFilePath(): void
    {
        $context = $this->createContext(
            ['src/Core/Framework/Api/ApiController.php', 'src/Core/Framework/Api/Clean.php'],
            "'src/Core/Framework/Api/ApiController.php'\n",
        );

        (new IgnoredPhpstanErrorsInTouchedFiles())($context);

        static::assertCount(1, $context->getFailures());
        static::assertStringContainsString('contain ignored PHPStan errors', $context->getFailures()[0]);
        static::assertStringContainsString('src/Core/Framework/Api/ApiController.php', $context->getFailures()[0]);
        static::assertStringNotContainsString('Clean.php', $context->getFailures()[0]);
    }

    #[TestDox('Fails when a touched file still has baselined PHPStan errors in yaml style')]
    public function testFailsForTouchedFileWithBaselinedErrorsWithYamlStyleConfig(): void
    {
        $context = $this->createContext(
            ['src/Core/Framework/Api/ApiController.php', 'src/Core/Framework/Api/Clean.php'],
            "path: src/Core/Framework/Api/ApiController.php\n",
        );

        (new IgnoredPhpstanErrorsInTouchedFiles())($context);

        static::assertCount(1, $context->getFailures());
        static::assertStringContainsString('contain ignored PHPStan errors', $context->getFailures()[0]);
        static::assertStringContainsString('src/Core/Framework/Api/ApiController.php', $context->getFailures()[0]);
        static::assertStringNotContainsString('Clean.php', $context->getFailures()[0]);
    }

    #[TestDox('Stays silent when no touched file appears in the baseline')]
    public function testSilentWhenTouchedFilesAreClean(): void
    {
        $context = $this->createContext(
            ['src/Core/Framework/Api/Clean.php'],
            "'path' => __DIR__ . 'src/Core/Framework/Api/ApiController.php'\n",
        );

        (new IgnoredPhpstanErrorsInTouchedFiles())($context);

        static::assertFalse($context->hasReports());
    }

    #[TestDox('The skip-danger-phpstan-baseline label disables the check, case-insensitively')]
    public function testSkipLabelDisablesTheCheck(): void
    {
        $context = $this->createContext(
            ['src/Core/Framework/Api/ApiController.php'],
            "'path' => __DIR__ . 'src/Core/Framework/Api/ApiController.php'\n",
            ['Skip-Danger-PHPStan-Baseline'],
        );

        (new IgnoredPhpstanErrorsInTouchedFiles())($context);

        static::assertFalse($context->hasReports());
    }

    /**
     * @param list<string> $touchedFiles
     * @param list<string> $labels
     */
    private function createContext(array $touchedFiles, string $baseline, array $labels = []): Context
    {
        $files = array_map(static fn (string $name): StubFile => new StubFile($name), $touchedFiles);

        return new Context(new StubPlatform(new StubPullRequest(
            $files,
            ['phpstan-baseline.php' => $baseline],
            $labels,
        )));
    }
}
