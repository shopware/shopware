<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Exception\MalformedUriException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use League\Flysystem\FilesystemOperator;
use Psr\Http\Message\UriInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('discovery')]
class TranslationArchiveLoader
{
    private const ARCHIVE_ROOT = 'translations';

    public function __construct(
        private readonly ClientInterface $client,
        private readonly FilesystemOperator $translationWriter,
        private readonly AbstractTranslationLoader $translationLoader,
        private readonly TranslationConfig $config,
        private readonly Filesystem $filesystem,
        private readonly string $cacheDir,
    ) {
    }

    /**
     * @param list<string> $locales
     */
    public function download(array $locales, ?\DateTimeInterface $newestUpdatedAt = null): void
    {
        foreach ($locales as $locale) {
            if (!$this->config->languages->has($locale)) {
                throw SnippetException::languageDoesNotExist($locale);
            }
        }

        $manifestUrl = $this->config->archiveManifestUrl;

        if ($manifestUrl === null) {
            throw SnippetException::translationArchiveUnavailable('no archive manifest is configured');
        }

        [$archiveUrl, $checksum, $committedAt] = $this->fetchManifest($manifestUrl);

        if ($newestUpdatedAt !== null && ($committedAt === null || $committedAt->getTimestamp() < $newestUpdatedAt->getTimestamp())) {
            throw SnippetException::translationArchiveUnavailable('the archive is older than the translation metadata');
        }

        try {
            $this->extractVerifiedArchive($archiveUrl, $checksum, $locales);
        } catch (IOException|UtilException $e) {
            throw SnippetException::translationArchiveUnavailable('the archive cannot be stored or verified', $e);
        }

        if ($this->translationLoader instanceof ResetInterface) {
            $this->translationLoader->reset();
        }
    }

    /**
     * @param list<string> $locales
     */
    private function extractVerifiedArchive(UriInterface $archiveUrl, string $checksum, array $locales): void
    {
        $archivePath = Path::join($this->cacheDir, 'translation-archive', Uuid::randomHex() . '.zip');

        try {
            $this->filesystem->mkdir(Path::getDirectory($archivePath));
            $this->fetchArchive($archiveUrl, $archivePath);

            if (!hash_equals($checksum, Hasher::hashFile($archivePath, 'sha256'))) {
                throw SnippetException::translationArchiveUnavailable(\sprintf('"%s" does not match the checksum of its manifest', $archiveUrl));
            }

            $this->extract($archivePath, $locales);
        } finally {
            $this->filesystem->remove($archivePath);
        }
    }

    /**
     * @return array{UriInterface, string, \DateTimeImmutable|null}
     */
    private function fetchManifest(Uri $manifestUrl): array
    {
        try {
            $manifest = json_decode($this->client->request('GET', $manifestUrl)->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);
        } catch (GuzzleException|\JsonException $e) {
            throw SnippetException::translationArchiveUnavailable(\sprintf('the manifest "%s" cannot be read', $manifestUrl), $e);
        }

        $archive = \is_array($manifest) ? ($manifest['archive'] ?? null) : null;
        $file = \is_array($archive) ? ($archive['file'] ?? null) : null;
        $checksum = \is_array($archive) ? ($archive['sha256'] ?? null) : null;

        if (!\is_string($file) || $file === '' || !\is_string($checksum)) {
            throw SnippetException::translationArchiveUnavailable(\sprintf('the manifest "%s" names no archive', $manifestUrl));
        }

        try {
            $archiveUrl = UriResolver::resolve($manifestUrl, new Uri($file));
        } catch (MalformedUriException $e) {
            throw SnippetException::translationArchiveUnavailable(\sprintf('the manifest "%s" names an invalid archive file', $manifestUrl), $e);
        }

        return [$archiveUrl, $checksum, $this->parseCommittedAt($manifest['committedAt'] ?? null)];
    }

    private function parseCommittedAt(mixed $committedAt): ?\DateTimeImmutable
    {
        if (!\is_string($committedAt)) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat(\DATE_ATOM, $committedAt) ?: null;
    }

    private function fetchArchive(UriInterface $archiveUrl, string $archivePath): void
    {
        try {
            $this->client->request('GET', $archiveUrl, ['sink' => $archivePath]);
        } catch (GuzzleException $e) {
            throw SnippetException::translationArchiveUnavailable(\sprintf('"%s" cannot be downloaded', $archiveUrl), $e);
        }
    }

    /**
     * @param list<string> $locales
     */
    private function extract(string $archivePath, array $locales): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($archivePath, \ZipArchive::RDONLY) !== true) {
            throw SnippetException::translationArchiveUnavailable('the archive is no zip file');
        }

        try {
            foreach ($locales as $locale) {
                $destinationByRemotePath = TranslationFileMap::forLocale($this->config, $locale, $this->translationLoader->getLocalePath($locale));

                foreach ($destinationByRemotePath as $remotePath => $destination) {
                    $content = $zip->getFromName(self::ARCHIVE_ROOT . '/' . $remotePath);

                    if ($content !== false) {
                        $this->translationWriter->write($destination, $content);
                    }
                }
            }
        } finally {
            $zip->close();
        }
    }
}
