<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\Util\TranslationCommandHelper;
use Shopware\Core\System\Snippet\Service\AbstractTranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataLoader;
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
#[AsCommand(
    name: 'translation:install',
    description: 'Downloads and installs translations from the translations GitHub repository for the specified locales or all available locales. Re-installing will overwrite existing translations.',
)]
#[Package('discovery')]
class InstallTranslationCommand extends Command
{
    public function __construct(
        private readonly AbstractTranslationLoader $translationLoader,
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
        $locales = $this->getLocales($input, $output);

        try {
            $metadata = $this->metadataLoader->getUpdatedLocalMetadata($locales);
        } catch (\Throwable $e) {
            TranslationCommandHelper::printMetadataLoadingFailed($output, $e);

            return self::FAILURE;
        }

        $localesRequiringUpdate = $metadata->getLocalesRequiringUpdate();
        if ($localesRequiringUpdate === []) {
            TranslationCommandHelper::printNoTranslationsToUpdate($output);

            return self::SUCCESS;
        }

        $localesDiff = array_diff($locales, $localesRequiringUpdate);
        if ($localesDiff !== []) {
            TranslationCommandHelper::printSkippedLocales($output, $localesDiff);
        }

        $context = Context::createCLIContext();
        $activate = !$input->getOption('skip-activation');

        TranslationCommandHelper::executeLoadWithProgressBar(
            $localesRequiringUpdate,
            $output,
            fn (string $locale) => $this->translationLoader->load($locale, $context, $activate),
        );

        $output->write(\PHP_EOL);

        TranslationCommandHelper::handleSavingMetadataCLIOutput(fn () => $this->metadataLoader->save($metadata), $output);

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

        $this->validateLocales($locales);

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

        $this->validateLocales($selected);

        return $selected;
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
}
