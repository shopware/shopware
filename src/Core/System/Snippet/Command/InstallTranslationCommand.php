<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'translation:install',
    description: 'Installs translations',
)]
#[Package('discovery')]
class InstallTranslationCommand extends Command
{
    private TranslationConfig $config;

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
        $this->config = $this->loadConfig();
    }

    private const TRANSLATION_DESTINATION = __DIR__ . '/../../Resources/translation';

    private const TRANSLATION_CONFIG_DIR = __DIR__ . '/../../Resources/translation/config';

    private const TRANSLATION_CONFIG_FILE = '/translation.yaml';

    private const PLATFORM_STRUCTURE = [
        'Platform' => [
            'Administration' => 'administration.json',
            'Core' => 'messages.json',
            'Storefront' => 'storefront.json',
        ],
    ];

    private const PLUGIN_DOMAINS = [
        'Storefront',
        'Administration',
    ];

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

            $this->fetchPluginSnippets($locale);

            foreach (self::PLATFORM_STRUCTURE as $bundle => $domains) {
                foreach ($domains as $domain => $fileName) {
                    $path = '/' . $locale . '/' . $bundle. '/' . $domain;

                    $this->fetchFile($path, $fileName);
                }
            }
        }

        $progressBar->finish();
        $output->write(PHP_EOL);

        return self::SUCCESS;
    }

    private function loadConfig(): TranslationConfig
    {
        $config = Yaml::parse(file_get_contents(realpath(self::TRANSLATION_CONFIG_DIR) . self::TRANSLATION_CONFIG_FILE));

        return TranslationConfig::create(
            $config['repository-url'],
            $config['locales'],
            $config['plugins'],
        );
    }

    private function getLocales(InputInterface $input): array
    {
        if ($input->getOption('all')) {
            return $this->config->locales;
        }

        $locales = $input->getOption('locales');

        if (!$locales) {
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
            throw new \InvalidArgumentException(sprintf('Invalid locale codes: %s. Available codes: %s', implode(', ', $errors), implode(', ', $this->config->locales)));
        }

        return $locales;
    }

    private function fetchPluginSnippets(string $locale): void
    {
        foreach ($this->config->plugins as $plugin) {
            foreach (self::PLUGIN_DOMAINS as $domain) {
                $fileName = strtolower($domain) . '.json';
                $path = '/' . $locale . '/Plugins/' . $plugin . '/' . $domain;

                $this->fetchFile($path, $fileName);
            }
        }
    }

    private function fetchFile(string $path, string $fileName): void
    {
        $destinationPath = realpath(self::TRANSLATION_DESTINATION) . $path;

        if (!$this->filesystem->exists($destinationPath)) {
            $this->filesystem->mkdir($destinationPath);
        }

        $url = $this->config->repositoryUrl . $path . '/' . $fileName;

        $this->downloadFile($url, $destinationPath . '/' . $fileName);
    }

    private function downloadFile(string $url, string $destination): void
    {
        $client = new Client();

        try {
            $client->request('GET', $url, ['sink' => $destination]);
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                // If the file does not exist, we can skip it
                return;
            }

            throw $e;
        }
    }

    private function createProgressBar(OutputInterface $output, int $count): ProgressBar
    {
        ProgressBar::setFormatDefinition('custom', '%current%/%max% -- Fetching translations for locale: %message%');
        $progressBar = new ProgressBar($output, $count);
        $progressBar->setFormat('custom');

        return $progressBar;
    }
}
