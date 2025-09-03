<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataLoader;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'translation:install',
    description: 'Downloads and installs translations from the translations GitHub repository for the specified locales or all available locales',
)]
#[Package('discovery')]
class InstallTranslationCommand extends Command
{
    public function __construct(
        private readonly TranslationLoader $translationLoader,
        private readonly TranslationConfig $config,
        private readonly TranslationMetadataLoader $metadataLoader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Fetch all available translations');
        $this->addOption('locales', null, InputOption::VALUE_OPTIONAL, 'Fetch translations for specific locale codes comma separated, e.g. "de-DE,en-US"');
        $this->addOption('skip-activation', null, InputOption::VALUE_NONE, 'Skip activation of created languages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locales = $this->getLocales($input);

        try {
            $metadata = $this->metadataLoader->getUpdatedMetadata($locales);
        } catch (\Throwable $e) {
            $output->writeln(\sprintf('<error>An error occurred while fetching metadata: "%s"</error>', $e->getMessage()));

            return self::FAILURE;
        }

        $localesRequiringUpdate = $metadata->getLocalesRequiringUpdate();
        if ($localesRequiringUpdate === []) {
            $output->writeln('All translations are already up to date.');

            return self::SUCCESS;
        }

        $localesDiff = array_diff($locales, $localesRequiringUpdate);
        if ($localesDiff !== []) {
            $output->writeln(\sprintf(
                'The following locales are already up to date and will be skipped: %s',
                implode(', ', $localesDiff)
            ));
        }

        $progressBar = $this->createProgressBar($output, \count($localesRequiringUpdate));
        $context = Context::createCLIContext();

        $activate = !$input->getOption('skip-activation');
        foreach ($localesRequiringUpdate as $locale) {
            $progressBar->setMessage($locale);
            $progressBar->advance();

            $this->translationLoader->load($locale, $context, $activate);
        }

        $progressBar->finish();
        $output->write(\PHP_EOL);

        $this->saveMetadata($metadata, $output);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function getLocales(InputInterface $input): array
    {
        if ($input->getOption('all')) {
            return $this->config->locales;
        }

        $locales = $input->getOption('locales');

        if (!$locales) {
            throw SnippetException::noArgumentsProvided();
        }

        $locales = explode(',', $locales);

        $this->validateLocales($locales);

        return $locales;
    }

    /**
     * @param list<string> $locales
     */
    private function validateLocales(array $locales): void
    {
        if ($locales === []) {
            throw SnippetException::noLocalesArgumentProvided();
        }

        $errors = [];
        foreach ($locales as $locale) {
            if (!\in_array($locale, $this->config->locales, true)) {
                $errors[] = $locale;
            }
        }

        if (!$errors) {
            return;
        }

        throw SnippetException::invalidLocalesProvided(
            implode(', ', $errors),
            implode(', ', $this->config->locales)
        );
    }

    private function createProgressBar(OutputInterface $output, int $count): ProgressBar
    {
        ProgressBar::setFormatDefinition('install-translations-format', '%current%/%max% -- Fetching translations for locale: %message%');
        $progressBar = new ProgressBar($output, $count);
        $progressBar->setFormat('install-translations-format');

        return $progressBar;
    }

    private function saveMetadata(MetadataCollection $metadata, OutputInterface $output): void
    {
        $output->writeln('Saving translation metadata...');

        try {
            $this->metadataLoader->save($metadata);
            $output->writeln('Translation metadata saved successfully.');
        } catch (\Throwable $e) {
            $output->writeln(\sprintf('<error>An error occurred while saving metadata: "%s"</error>', $e->getMessage()));
        }
    }
}
