<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\Util\TranslationCommandHelper;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
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
        $this->addOption('locales', null, InputOption::VALUE_OPTIONAL, 'Fetch translations for specific locale codes comma separated, e.g. "es-ES,en-US"');
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
        $localesWithFiles = $this->localesWithTranslationFiles($locales, $localesRequiringUpdate);

        $unavailable = $this->unavailableLocales($locales, $metadata, $localesWithFiles);

        if ($this->everyRequestedLocaleIsUnavailable($locales, $unavailable)) {
            throw SnippetException::translationsUnavailable($unavailable);
        }

        if ($unavailable !== []) {
            TranslationCommandHelper::printUnavailableLocales($output, $unavailable);
        }

        $installable = array_values(array_diff($locales, $unavailable));

        if ($localesRequiringUpdate === []) {
            TranslationCommandHelper::printNoTranslationsToUpdate($output);
        }

        $localesToLink = $this->localesToLink($installable, $localesWithFiles);
        if ($localesToLink !== []) {
            TranslationCommandHelper::printLocalesInstalledFromExistingFiles($output, $localesToLink);
        }

        $context = Context::createCLIContext();

        TranslationCommandHelper::executeLoadWithProgressBar(
            $installable,
            $output,
            fn (string $locale) => $this->installLocale($locale, $localesToLink, $context, $activate),
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
     * Every locale is verified before the first one is linked, so an incomplete provisioning step
     * reports all of its missing locales at once and leaves no half-installed state behind.
     *
     * @param list<string> $locales
     */
    private function installOffline(array $locales, bool $activate, OutputInterface $output): int
    {
        $missing = array_values(array_filter(
            $locales,
            fn (string $locale) => !$this->translationLoader->hasTranslationFiles($locale),
        ));

        if ($missing !== []) {
            throw SnippetException::translationsUnavailable($missing);
        }

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
     * @param list<string> $localesToLink
     */
    private function installLocale(string $locale, array $localesToLink, Context $context, bool $activate): void
    {
        if (\in_array($locale, $localesToLink, true)) {
            $this->translationLoader->link($locale, $context, $activate);

            return;
        }

        $this->translationLoader->load($locale, $context, $activate);
    }

    /**
     * Locales that are not re-downloaded anyway and already carry files. Both the unavailable set and
     * the link set are derived from this, so every locale is checked once: on a remote filesystem each
     * check is a request.
     *
     * @param list<string> $locales
     * @param list<string> $localesRequiringUpdate
     *
     * @return list<string>
     */
    private function localesWithTranslationFiles(array $locales, array $localesRequiringUpdate): array
    {
        return array_values(array_filter(
            array_diff($locales, $localesRequiringUpdate),
            fn (string $locale) => $this->translationLoader->hasTranslationFiles($locale),
        ));
    }

    /**
     * Whether a translation is up to date and whether it is actually installed are two different
     * questions. These locales keep the files they have, because the repository has nothing newer,
     * but their language and snippet set are ensured just the same.
     *
     * @param list<string> $locales
     * @param list<string> $localesWithFiles
     *
     * @return list<string>
     */
    private function localesToLink(array $locales, array $localesWithFiles): array
    {
        return array_values(array_intersect($locales, $localesWithFiles));
    }

    /**
     * Requested locales the translation repository does not offer and that have no files on the
     * filesystem either. Installing them would create a language with no translations behind it,
     * so they are reported and left out instead.
     *
     * @param list<string> $locales
     * @param list<string> $localesWithFiles
     *
     * @return list<string>
     */
    private function unavailableLocales(array $locales, MetadataCollection $metadata, array $localesWithFiles): array
    {
        return array_values(array_diff($locales, $metadata->getKeys(), $localesWithFiles));
    }

    /**
     * @param list<string> $locales
     * @param list<string> $unavailable
     */
    private function everyRequestedLocaleIsUnavailable(array $locales, array $unavailable): bool
    {
        return $unavailable !== [] && \count($unavailable) === \count($locales);
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
