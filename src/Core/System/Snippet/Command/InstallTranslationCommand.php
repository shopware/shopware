<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
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
    private TranslationConfig $config;

    public function __construct(
        private readonly TranslationLoader $translationLoader,
    ) {
        parent::__construct();
        $this->config = TranslationLoader::loadConfig();
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

        foreach ($locales as $locale) {
            $progressBar->setMessage($locale);
            $progressBar->advance();

            $this->translationLoader->load($locale);
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
            // todo: custom domain exception
            throw new \InvalidArgumentException('You must specify either --all or --locales option.');
        }

        $locales = explode(',', $locales);

        if ($locales === []) {
            throw new \InvalidArgumentException('The --locales option must not be empty.');
        }

        $errors = [];
        foreach ($locales as $locale) {
            if (!\in_array($locale, $this->config->locales, true)) {
                $errors[] = $locale;
            }
        }

        if ($errors) {
            throw new \InvalidArgumentException(\sprintf('Invalid locale codes: %s. Available codes: %s', implode(', ', $errors), implode(', ', $this->config->locales)));
        }

        return $locales;
    }

    private function createProgressBar(OutputInterface $output, int $count): ProgressBar
    {
        ProgressBar::setFormatDefinition('custom', '%current%/%max% -- Fetching translations for locale: %message%');
        $progressBar = new ProgressBar($output, $count);
        $progressBar->setFormat('custom');

        return $progressBar;
    }
}
