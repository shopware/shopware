<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\Util\TranslationCommandHelper;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('discovery')]
#[AsCommand(
    name: 'translation:install',
    description: 'Downloads and installs translations from the translations GitHub repository for the specified locales or all available locales. Re-installing will overwrite existing translations.',
)]
class InstallTranslationCommand extends Command
{
    public function __construct(
        private readonly AbstractTranslationLoader $translationLoader,
        private readonly TranslationConfig $config,
        private readonly TranslationMetadataStore $metadataStore,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Fetch all available translations');
        $this->addOption('locales', null, InputOption::VALUE_OPTIONAL, 'Fetch translations for specific locale codes comma separated, e.g. "de-DE,en-US"');
        $this->addOption('skip-activation', null, InputOption::VALUE_NONE, 'Skip activation of created languages');
        $this->addOption('offline', null, InputOption::VALUE_NONE, 'Install from translation files that are already on the filesystem, without contacting the translation repository');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locales = $this->getLocales($input, $output);
        $activate = !$input->getOption('skip-activation');

        if ($input->getOption('offline')) {
            return $this->installOffline($locales, $activate, $output);
        }

        try {
            $metadata = $this->metadataStore->getUpdatedLocalMetadata($locales);
        } catch (\Throwable $e) {
            TranslationCommandHelper::printMetadataLoadingFailed($output, $e);

            return self::FAILURE;
        }

        $localesRequiringUpdate = $metadata->getLocalesRequiringUpdate();

        if ($localesRequiringUpdate === []) {
            TranslationCommandHelper::printNoTranslationsToUpdate($output);
        }

        $localesDiff = array_diff($locales, $localesRequiringUpdate);
        if ($localesDiff !== []) {
            TranslationCommandHelper::printLocalesNotDownloadedAgain($output, $localesDiff);
        }

        $context = Context::createCLIContext();

        TranslationCommandHelper::executeLoadWithProgressBar(
            $locales,
            $output,
            function (string $locale) use ($context, $activate, $localesRequiringUpdate): void {
                // Whether a translation is up to date and whether it is actually installed are
                // two different questions. Files are only re-fetched when the repository has
                // something newer, or when they are missing locally, but the language and the
                // snippet set are ensured for every requested locale either way.
                if (\in_array($locale, $localesRequiringUpdate, true) || !$this->translationLoader->hasTranslationFiles($locale)) {
                    $this->translationLoader->load($locale, $context, $activate);

                    return;
                }

                $this->translationLoader->link($locale, $context, $activate);
            },
        );

        $output->write(\PHP_EOL);

        if ($localesRequiringUpdate !== []) {
            TranslationCommandHelper::handleSavingMetadataCLIOutput(fn () => $this->metadataStore->save($metadata), $output);
        }

        return self::SUCCESS;
    }

    /**
     * The metadata store is deliberately left untouched here. Reading it would contact the
     * translation repository, which is the one thing this mode promises not to do, and writing
     * it would make a later run believe every locale is current and skip creating the
     * languages it is being asked for.
     *
     * @param list<string> $locales
     */
    private function installOffline(array $locales, bool $activate, OutputInterface $output): int
    {
        $context = Context::createCLIContext();

        TranslationCommandHelper::executeLoadWithProgressBar(
            $locales,
            $output,
            fn (string $locale) => $this->translationLoader->link($locale, $context, $activate),
        );

        $output->write(\PHP_EOL);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function getLocales(InputInterface $input, OutputInterface $output): array
    {
        if ($input->getOption('all')) {
            return $this->config->locales;
        }

        $locales = $input->getOption('locales');

        if (!$locales) {
            if ($input->isInteractive()) {
                return $this->askLocales($input, $output);
            }

            throw SnippetException::noArgumentsProvided();
        }

        $locales = explode(',', $locales);

        $this->config->assertLocalesAreConfigured($locales);

        return $locales;
    }

    /**
     * @return list<string>
     */
    private function askLocales(InputInterface $input, OutputInterface $output): array
    {
        $choices = [];
        foreach ($this->config->languages as $language) {
            $choices[$language->locale] = $language->name;
        }

        if ($choices === []) {
            foreach ($this->config->locales as $locale) {
                $choices[$locale] = $locale;
            }
        }

        ksort($choices);

        $question = new ChoiceQuestion(
            'Select one or more locales to install (comma-separated locale codes, e.g. "de-AT,fr-FR")',
            $choices,
        );
        $question->setMultiselect(true);
        $question->setErrorMessage('Locale "%s" is invalid.');

        $locales = array_keys($choices);
        $question->setAutocompleterCallback(static function (string $userInput) use ($locales): array {
            $trailingComma = strrpos($userInput, ',');
            $prefix = $trailingComma === false ? '' : substr($userInput, 0, $trailingComma + 1);
            $current = ltrim($trailingComma === false ? $userInput : substr($userInput, $trailingComma + 1));

            $suggestions = [];
            foreach ($locales as $locale) {
                if ($current === '' || str_starts_with($locale, $current)) {
                    $suggestions[] = $prefix . $locale;
                }
            }

            return $suggestions;
        });

        /** @var list<string> $selected */
        $selected = (new SymfonyStyle($input, $output))->askQuestion($question);

        $this->config->assertLocalesAreConfigured($selected);

        return $selected;
    }
}
