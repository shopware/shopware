<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationLoader
{
    private const TRANSLATION_DESTINATION = __DIR__ . '/../../Resources/translation';

    private const TRANSLATION_CONFIG_DIR = __DIR__ . '/../../Resources';

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

    private TranslationConfig $config;

    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
        $this->config = self::loadConfig();
    }

    public function load(string $locale): void
    {
        $this->fetchPluginSnippets($locale);

        foreach (self::PLATFORM_STRUCTURE as $bundle => $domains) {
            foreach ($domains as $domain => $fileName) {
                $path = '/' . $locale . '/' . $bundle . '/' . $domain;

                $this->fetchFile($path, $fileName);
            }
        }
    }

    public static function loadConfig(): TranslationConfig
    {
        // todo: implement error handling
        $config = Yaml::parse(file_get_contents(realpath(self::TRANSLATION_CONFIG_DIR) . self::TRANSLATION_CONFIG_FILE));

        return TranslationConfig::create(
            $config['repository-url'],
            $config['locales'],
            $config['plugins'],
        );
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
}
