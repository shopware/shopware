<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @phpstan-type DecodedMetadata array<string, array{locale: string, updatedAt: string, progress: int}>
 */
#[Package('discovery')]
class TranslationMetadataLoader
{
    private const CROWDIN_METADATA_LOCK = 'crowdin-metadata.lock';

    public function __construct(
        private readonly TranslationConfig $config,
        private readonly ClientInterface $client,
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * @param list<string>|null $locales
     *
     * Updates the local metadata with the latest remote metadata and returns the updated collection.
     * If locales are provided, only those locales will be updated; otherwise all installed locales will be updated.
     */
    public function getUpdatedLocalMetadata(?array $locales = null): MetadataCollection
    {
        $localMetadata = $this->getLocalMetadata();
        $remoteMetadata = $this->fetchRemoteMetadataArray();

        $locales = $locales ?? $localMetadata->getKeys();

        foreach ($locales as $locale) {
            $remoteEntry = $remoteMetadata[$locale] ?? null;

            if ($remoteEntry === null) {
                continue;
            }

            $remoteResult = MetadataEntry::create($remoteEntry);
            $localMetadata->addIfRequired($remoteResult);
        }

        return $localMetadata;
    }

    public function save(MetadataCollection $remoteMetadata): void
    {
        $path = $this->getPath();

        $this->filesystem->write(
            $path,
            json_encode($remoteMetadata->jsonSerialize(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT),
        );
    }

    protected function getPath(): string
    {
        return Path::join(TranslationLoader::TRANSLATION_DIR, self::CROWDIN_METADATA_LOCK);
    }

    private function downloadFile(): ResponseInterface
    {
        try {
            return $this->client->request(Request::METHOD_GET, $this->config->metadataUrl);
        } catch (GuzzleException $e) {
            throw SnippetException::translationMetadataDownloadFailed($this->config->metadataUrl, $e);
        }
    }

    /**
     * @return DecodedMetadata
     */
    private function decode(string $content): array
    {
        $data = json_decode($content, true, \JSON_THROW_ON_ERROR);

        return array_column($data, null, 'locale');
    }

    private function getLocalMetadata(): MetadataCollection
    {
        $path = $this->getPath();

        try {
            $localMetadata = $this->filesystem->read($path);
        } catch (FilesystemException) {
            return new MetadataCollection();
        }

        $localMetadata = $this->decode($localMetadata);

        $elements = [];
        foreach ($localMetadata as $metadata) {
            $elements[] = MetadataEntry::create($metadata);
        }

        return new MetadataCollection($elements);
    }

    /**
     * @return DecodedMetadata
     */
    private function fetchRemoteMetadataArray(): array
    {
        $response = $this->downloadFile();
        $content = $response->getBody()->getContents();

        return $this->decode($content);
    }
}
