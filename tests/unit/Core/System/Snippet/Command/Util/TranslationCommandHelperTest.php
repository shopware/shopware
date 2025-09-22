<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\Util\TranslationCommandHelper;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(TranslationCommandHelper::class)]
class TranslationCommandHelperTest extends TestCase
{
    public function testHandleSavingMetadataCLIOutput(): void
    {
        $success = false;
        $callback = function () use (&$success): void {
            $success = true;
        };

        $output = new BufferedOutput();
        TranslationCommandHelper::handleSavingMetadataCLIOutput($callback, $output);

        static::assertTrue($success);

        $content = $output->fetch();
        static::assertStringContainsString('Saving translation metadata...', $content);
        static::assertStringContainsString('Translation metadata saved successfully.', $content);
    }

    public function testHandleSavingMetadataCliOutputHandlesExceptions(): void
    {
        $callback = function (): void {
            throw new \RuntimeException('Test exception');
        };

        $output = new BufferedOutput();
        TranslationCommandHelper::handleSavingMetadataCLIOutput($callback, $output);

        $content = $output->fetch();
        static::assertStringContainsString('Saving translation metadata...', $content);
        static::assertStringContainsString('An error occurred while saving metadata: "Test exception"', $content);
    }

    public function testExecuteLoadWithProgressBar(): void
    {
        $locales = ['en-GB', 'de-DE', 'fr-FR'];
        $loadedLocales = [];

        $callback = function (string $locale) use (&$loadedLocales): void {
            $loadedLocales[] = $locale;
        };

        $output = new BufferedOutput();
        TranslationCommandHelper::executeLoadWithProgressBar($locales, $output, $callback);

        static::assertSame($locales, $loadedLocales);

        $content = $output->fetch();

        static::assertStringContainsString('1/3 -- Fetching translations for locale: en-GB', $content);
        static::assertStringContainsString('3/3 -- Fetching translations for locale: fr-FR', $content);
    }

    public function testPrintMetadataLoadingFailed(): void
    {
        $output = new BufferedOutput();
        $exception = new \RuntimeException('Test exception');

        TranslationCommandHelper::printMetadataLoadingFailed($output, $exception);

        $content = $output->fetch();
        static::assertStringContainsString('An error occurred while fetching metadata: "Test exception"', $content);
    }

    public function testPrintNoTranslationsToUpdate(): void
    {
        $output = new BufferedOutput();

        TranslationCommandHelper::printNoTranslationsToUpdate($output);

        $content = $output->fetch();
        static::assertStringContainsString('All translations are already up to date.', $content);
    }

    public function testPrintSkippedLocales(): void
    {
        $output = new BufferedOutput();
        $localesDiff = ['de-DE', 'fr-FR'];

        TranslationCommandHelper::printSkippedLocales($output, $localesDiff);

        $content = $output->fetch();
        static::assertStringContainsString('The following locales are already up to date and will be skipped: de-DE, fr-FR', $content);
    }
}
