<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command\Util;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationCommandHelper
{
    private const PROGRESS_BAR_NAME = 'install-translations-format';

    private const PROGRESS_BAR_FORMAT = '%current%/%max% -- Fetching translations for locale: %message%';

    public static function handleSavingMetadataCLIOutput(callable $saveCallback, OutputInterface $output): void
    {
        $output->writeln('Saving translation metadata...');

        try {
            $saveCallback();
            $output->writeln('Translation metadata saved successfully.');
        } catch (\Throwable $e) {
            $output->writeln(\sprintf('<error>An error occurred while saving metadata: "%s"</error>', $e->getMessage()));
        }
    }

    public static function createProgressBar(OutputInterface $output, int $max): ProgressBar
    {
        ProgressBar::setFormatDefinition(self::PROGRESS_BAR_NAME, self::PROGRESS_BAR_FORMAT);
        $progressBar = new ProgressBar($output, $max);
        $progressBar->setFormat(self::PROGRESS_BAR_NAME);

        return $progressBar;
    }

    /**
     * @param list<string> $locales
     */
    public static function executeLoadWithProgressBar(array $locales, OutputInterface $output, callable $loadCallback): void
    {
        $progressBar = self::createProgressBar($output, \count($locales));

        foreach ($locales as $locale) {
            $progressBar->setMessage($locale);
            $progressBar->advance();

            $loadCallback($locale);
        }

        $progressBar->finish();
    }

    public static function printMetadataLoadingFailed(OutputInterface $output, \Throwable $e): void
    {
        $output->writeln(\sprintf('<error>An error occurred while fetching metadata: "%s"</error>', $e->getMessage()));
    }

    public static function printNoTranslationsToUpdate(OutputInterface $output): void
    {
        $output->writeln('All translations are already up to date.');
    }

    /**
     * @param non-empty-array<int, string> $localesDiff
     */
    public static function printSkippedLocales(OutputInterface $output, array $localesDiff): void
    {
        $output->writeln(\sprintf(
            'The following locales are already up to date and will be skipped: %s',
            implode(', ', $localesDiff)
        ));
    }

    /**
     * @param non-empty-array<int, string> $locales
     */
    public static function printLocalesInstalledFromExistingFiles(OutputInterface $output, array $locales): void
    {
        $output->writeln(\sprintf(
            'The following locales are installed from their existing translation files, without downloading: %s',
            implode(', ', $locales)
        ));
    }

    /**
     * @param non-empty-array<int, string> $locales
     */
    public static function printUnavailableLocales(OutputInterface $output, array $locales): void
    {
        $output->writeln(\sprintf(
            '<comment>No translations are available for the following locales, they will not be installed: %s</comment>',
            implode(', ', $locales)
        ));
    }

    /**
     * Unlike printUnavailableLocales() this says nothing about what the repository offers: in offline mode the
     * repository is never contacted, only the filesystem is.
     *
     * @param non-empty-array<int, string> $locales
     */
    public static function printLocalesWithoutFiles(OutputInterface $output, array $locales): void
    {
        $output->writeln(\sprintf(
            '<comment>No translation files are present for the following locales, they will not be installed: %s</comment>',
            implode(', ', $locales)
        ));
    }
}
