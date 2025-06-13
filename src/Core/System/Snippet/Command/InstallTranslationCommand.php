<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Command;

use Shopware\Core\Content\Media\File\FileFetcher;
use Shopware\Core\Content\Media\File\FileInfoHelper;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'translation:install',
    description: 'Installs translations',
)]
#[Package('discovery')]
class InstallTranslationCommand extends Command
{
    public function __construct(
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    private const RAW_GITHUB_URL = 'https://raw.githubusercontent.com/shopware/translations/main/translations';

    private const TRANSLATIONS_DESTINATION = __DIR__ . '/../../Resources/translation';

    private const ISO_CODES = [
        /*todo: this one is not a translation, it is a workaround: "ach-UG",*/ "ar-SA", "bg-BG", "bs-BA", "ca-ES", "cs-CZ", "da-DK",
        "de-AT", "de-CH", "de-DE", "el-GR", "en-GB", "en-US", "es-AR",
        "es-ES", "et-EE", "fi-FI", "fr-FR", "hi-IN", "hr-HR", "hu-HU",
        "hy-AM", "id-ID", "it-IT", "ja-JP", "ko-KR", "lt-LT", "lv-LV",
        "nl-NL", "nn-NO", "pl-PL", "pt-PT", "ro-RO", "ru-RU", "sk-SK",
        "sl-SI", "sr-RS", "sv-SE", "th-TH", "tr-TR", "uk-UA", "vi-VN",
    ];

    private const BUNDLE_STRUCTURE = [
        'Platform' => [
            'Administration' => 'administration.json',
            'Core' => 'messages.json',
            'Storefront' => 'storefront.json',
        ],
        'Plugins' => [ // todo: check
            'PluginPublisher' => ['Storefront', 'Administration'],
            'SwagB2bPlatform' => ['Storefront', 'Administration'],
            'SwagCmsExtensions' => ['Storefront', 'Administration'],
            'SwagCommercial' => ['Storefront', 'Administration'],
            'SwagCustomizedProducts' => ['Storefront', 'Administration'],
            'SwagEnterpriseSearch' => ['Storefront', 'Administration'],
            'SwagMigrationAssistant' => ['Administration'],
            'SwagMigrationMagento' => ['Administration'],
            'SwagPaypal' => ['Storefront', 'Administration'],
            'SwagSocialShopping' => ['Administration'],
        ],
    ];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (self::ISO_CODES as $isoCode) {
            foreach (self::BUNDLE_STRUCTURE as $bundle => $domains) {
                foreach ($domains as $domain => $fileName) {
                    if (\is_array($fileName)) {
                        $this->handleBundles($fileName, $isoCode, $bundle, $domain);
                        continue;
                    }

                    $path = '/' . $isoCode . '/' . $bundle. '/' . $domain;
                    $destinationPath = realpath(self::TRANSLATIONS_DESTINATION) . $path;

                    if (!$this->filesystem->exists($destinationPath)) {
                        $this->filesystem->mkdir($destinationPath);
                    }

                    $url = self::RAW_GITHUB_URL . $path . '/' . $fileName;

                    try {
                        $inputStream = $this->openStream($url, 'r', stream_context_create([
                            'http' => [
                                'follow_location' => 0,
                                'max_redirects' => 0,
                            ],
                        ]));
                    } catch (MediaException $e/* todo: use own exception*/) {
                        if ($e->getStatusCode() === 400) {
                            continue;
                        }
                    }

                    $destStream = $this->openStream($destinationPath . '/' . $fileName, 'w');

                    try {
                        $this->copyStreams($inputStream, $destStream);
                    } finally {
                        fclose($inputStream);
                        fclose($destStream);
                    }
                }
            }
        }

        return self::SUCCESS;
    }

    private function handleBundles(array $domains, string $isoCode, string $bundle, string $domain): void
    {
        foreach ($domains as $subdomain) {
            $fileName = strtolower($subdomain) . '.json';

            $path = '/' . $isoCode . '/' . $bundle. '/' . $domain . '/' . $subdomain;
            $destinationPath = realpath(self::TRANSLATIONS_DESTINATION) . $path;

            if (!$this->filesystem->exists($destinationPath)) {
                $this->filesystem->mkdir($destinationPath);
            }

            $url = self::RAW_GITHUB_URL . $path . '/' . $fileName;

            try {
                $inputStream = $this->openStream($url, 'r', stream_context_create([
                    'http' => [
                        'follow_location' => 0,
                        'max_redirects' => 0,
                    ],
                ]));
            } catch (MediaException $e/* todo: use own exception*/) {
                if ($e->getStatusCode() === 400) {
                    continue;
                }

                dd($e);
            }

            $destStream = $this->openStream($destinationPath . '/' . $fileName, 'w');

            try {
                $this->copyStreams($inputStream, $destStream);
            } finally {
                fclose($inputStream);
                fclose($destStream);
            }
        }
    }

    private function openStream(string $url, string $mode, $streamContext = null)
    {
        try {
            $stream = @fopen($url, $mode, false, $streamContext);
        } catch (\Throwable) {
        }

        if ($stream === false) {
            throw MediaException::cannotOpenSourceStreamToRead($url);
        }

        return $stream;
    }

    /**
     * @param resource $sourceStream
     * @param resource $destStream
     */
    private function copyStreams($sourceStream, $destStream/*, int $maxFileSize = 0*/): int
    {
        /*if ($maxFileSize === 0) {
            $writtenBytes = stream_copy_to_stream($sourceStream, $destStream);
            if ($writtenBytes === false) {
                throw MediaException::cannotCopyMedia();
            }

            return $writtenBytes;
        }*/

        $writtenBytes = stream_copy_to_stream($sourceStream, $destStream/*, $maxFileSize*/);
        if ($writtenBytes === false) {
            throw MediaException::cannotCopyMedia();
        }

        /*if ($writtenBytes === $maxFileSize) {
            throw MediaException::fileSizeLimitExceeded();
        }*/

        return $writtenBytes;
    }
}
