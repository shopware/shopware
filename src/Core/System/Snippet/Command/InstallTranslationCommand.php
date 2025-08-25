<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
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
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Fetch all available translations');
        $this->addOption('locales', null, InputOption::VALUE_OPTIONAL, 'Fetch translations for specific locale codes comma separated, e.g. "de-DE,en-US"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locales = $this->getLocales($input);
        $progressBar = $this->createProgressBar($output, \count($locales));
        $context = Context::createCLIContext();

        foreach ($locales as $locale) {
            $progressBar->setMessage($locale);
            $progressBar->advance();

            $this->translationLoader->load($locale, $context);
        }

        $progressBar->finish();
        $output->write(\PHP_EOL);

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
}
