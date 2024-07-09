<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\App\Exception\AppDownloadException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('core')]
class AppDownloader
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly Filesystem $filesystem = new Filesystem()
    ) {
    }

    public function download(string $zipUrl, string $destinationFile): void
    {
        $this->filesystem->mkdir(\dirname($destinationFile));

        // TODO: use psr request and dispatch an event?
        $response = $this->client->request('GET', $zipUrl, [
            'headers' => [
                'Accept' => 'application/zip',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw AppDownloadException::transportError($zipUrl);
        }

        foreach ($this->client->stream($response) as $chunk) {
            $this->filesystem->appendToFile($destinationFile, $chunk->getContent());
        }
    }

    public function downloadFromFilesystem(FilesystemOperator $filesystem, string $zipLocation, string $destinationFile): void
    {
        try {
            $contents = $filesystem->readStream($zipLocation);
        } catch (FilesystemException $e) {
            throw AppDownloadException::transportError($zipLocation, $e);
        }

        $this->filesystem->dumpFile($destinationFile, $contents);
    }
}
