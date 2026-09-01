<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\Util\TranslationCommandHelper;
use Shopware\Core\System\Snippet\DataTransfer\TranslationUpdate\TranslationInstallPlan;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Service\TranslationUpdater;
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
        private readonly TranslationConfig $config,
        private readonly TranslationMetadataStore $metadataStore,
        private readonly TranslationUpdater $translationUpdater,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Install all available translations');
        $this->addOption('locales', null, InputOption::VALUE_OPTIONAL, 'Install translations for specific locale codes comma separated, e.g. "es-ES,en-US"');
        $this->addOption('skip-activation', null, InputOption::VALUE_NONE, 'Skip activation of created languages');
        $this->addOption('offline', null, InputOption::VALUE_NONE, 'Install from translation files that are already on the filesystem, without contacting the translation repository');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locales = $this->getLocales($input, $output);
        $activate = !$input->getOption('skip-activation');

        if ($input->getOption('offline')) {
            return $this->installOffline($locales, $activate, (bool) $input->getOption('all'), $output);
        }

        try {
            $metadata = $this->metadataStore->getUpdatedLocalMetadata($locales);
        } catch (\Throwable $e) {
            TranslationCommandHelper::printMetadataLoadingFailed($output, $e);

            return self::FAILURE;
        }

        $plan = $this->translationUpdater->planInstall($locales, $metadata);

        if ($plan->nothingCanBeInstalled()) {
            throw SnippetException::translationsUnavailable($plan->unavailableLocales);
        }

        if ($plan->unavailableLocales !== []) {
            TranslationCommandHelper::printUnavailableLocales($output, $plan->unavailableLocales);
        }

        // Not "nothing requires an update": a locale whose metadata is current is still fetched when its files are gone
        if ($plan->localesToDownload === []) {
            TranslationCommandHelper::printNoTranslationsToUpdate($output);
        }

        if ($plan->localesToLink !== []) {
            TranslationCommandHelper::printLocalesInstalledFromExistingFiles($output, $plan->localesToLink);
        }

        $this->installWithProgressBar($plan, $activate, $output);

        if ($metadata->getLocalesRequiringUpdate() !== []) {
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
    private function installOffline(array $locales, bool $activate, bool $allRequested, OutputInterface $output): int
    {
        $plan = $this->translationUpdater->planOfflineInstall($locales);

        if ($this->offlineInstallMustFail($plan, $allRequested)) {
            throw SnippetException::translationsUnavailable($plan->unavailableLocales);
        }

        if ($plan->unavailableLocales !== []) {
            TranslationCommandHelper::printLocalesWithoutFiles($output, $plan->unavailableLocales);
        }

        $this->installWithProgressBar($plan, $activate, $output);

        return self::SUCCESS;
    }

    private function installWithProgressBar(TranslationInstallPlan $plan, bool $activate, OutputInterface $output): void
    {
        $progressBar = TranslationCommandHelper::createProgressBar(
            $output,
            \count($plan->localesToDownload) + \count($plan->localesToLink),
            'Installing translations',
        );

        $this->translationUpdater->install(
            $plan,
            Context::createCLIContext(),
            $activate,
            static function (string $locale) use ($progressBar): void {
                $progressBar->setMessage($locale);
                $progressBar->advance();
            },
        );

        $progressBar->finish();
        $output->write(\PHP_EOL);
    }

    private function offlineInstallMustFail(TranslationInstallPlan $plan, bool $allRequested): bool
    {
        if ($plan->unavailableLocales === []) {
            return false;
        }

        // --locales is a contract and fails as a unit; --all installs whatever is provisioned, unless nothing is
        return !$allRequested || $plan->localesToLink === [];
    }

    /**
     * @return list<string>
     */
    private function getLocales(InputInterface $input, OutputInterface $output): array
    {
        if ($input->getOption('all')) {
            // A pseudo-locale is a proofreading tool rather than a language a shop offers, so
            // "all" does not mean it. It stays installable by naming it in --locales, which is
            // how the audits it exists for ask for it.
            return array_values(array_diff($this->config->locales, $this->config->pseudoLocales));
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
