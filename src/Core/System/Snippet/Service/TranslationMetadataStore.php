<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Http\Message\ResponseInterface;
use Shopware\Core\Framework\Deployment\AirGappedMode;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataCollection;
use Shopware\Core\System\Snippet\DataTransfer\Metadata\MetadataEntry;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 *
 * @phpstan-type DecodedMetadata array<string, array{locale: string, updatedAt: string, progress: int}>
 */
#[Package('discovery')]
class TranslationMetadataStore
{
    private const CROWDIN_METADATA_LOCK = 'crowdin-metadata.lock';

    private const REMOTE_METADATA_CACHE_KEY = 'shopware.translation.remote_metadata';

    private const REMOTE_METADATA_CACHE_TTL = 300;

    public function __construct(
        private readonly TranslationConfig $config,
        private readonly ClientInterface $client,
        private readonly FilesystemOperator $filesystem,
        private readonly CacheInterface $cache,
        private readonly AirGappedMode $airGappedMode,
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

    public function remove(string $locale): void
    {
        $metadata = $this->getLocalMetadata();

        if (!$metadata->has($locale)) {
            return;
        }

        $metadata->remove($locale);
        $this->save($metadata);
    }

    /**
     * Builds the per-language translation list by merging the configured languages with their locally
     * installed and remotely available metadata, exposing install state, progress and update availability.
     * When the remote source is unreachable the list still renders from local metadata only.
     *
     * @return list<array{locale: string, name: string, lastUpdate: string|null, progress: int|null, updateAvailable: bool, isPseudoLanguage: bool}>
     */
    public function getTranslationList(): array
    {
        $installed = $this->getLocalMetadata();
        $remote = $this->getRemoteMetadataOrEmpty();

        $items = [];
        foreach ($this->config->languages as $language) {
            $installedEntry = $installed->get($language->locale);
            $remoteEntry = $remote->get($language->locale);

            $items[] = [
                'locale' => $language->locale,
                'name' => $language->name,
                'lastUpdate' => $installedEntry?->updatedAt->format(\DateTimeInterface::ATOM),
                'progress' => $remoteEntry?->progress,
                'updateAvailable' => $installedEntry !== null
                    && $remoteEntry !== null
                    && $installedEntry->updatedAt->getTimestamp() !== $remoteEntry->updatedAt->getTimestamp(),
                'isPseudoLanguage' => \in_array($language->locale, $this->config->pseudoLocales, true),
            ];
        }

        return $items;
    }

    public function getRemoteMetadata(): MetadataCollection
    {
        $elements = [];
        foreach ($this->fetchRemoteMetadataArray() as $metadata) {
            $elements[] = MetadataEntry::create($metadata);
        }

        return new MetadataCollection($elements);
    }

    public function getLocalMetadata(): MetadataCollection
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

    protected function getPath(): string
    {
        return Path::join(AbstractTranslationLoader::TRANSLATION_DIR, self::CROWDIN_METADATA_LOCK);
    }

    private function getRemoteMetadataOrEmpty(): MetadataCollection
    {
        try {
            /*
             * Cache the remote metadata briefly for this read-only path: the admin requests the translation list on
             * every language list/detail load, while the remote source only changes roughly once a day. The
             * install/update path deliberately bypasses this cache and always fetches fresh metadata, so it can still
             * detect newer remote versions. Failures propagate out of the callback and are not cached.
             */
            $elements = $this->cache->get(self::REMOTE_METADATA_CACHE_KEY, function (ItemInterface $item): array {
                $item->expiresAfter(self::REMOTE_METADATA_CACHE_TTL);

                return $this->fetchRemoteMetadataArray();
            });

            $collection = [];
            foreach ($elements as $metadata) {
                $collection[] = MetadataEntry::create($metadata);
            }

            return new MetadataCollection($collection);
        } catch (\Throwable) {
            return new MetadataCollection();
        }
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
        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        return array_column($data, null, 'locale');
    }

    /**
     * @return DecodedMetadata
     */
    private function fetchRemoteMetadataArray(): array
    {
        if ($this->airGappedMode->isEnabled()) {
            return [];
        }

        $response = $this->downloadFile();
        $content = $response->getBody()->getContents();

        return $this->decode($content);
    }
}
