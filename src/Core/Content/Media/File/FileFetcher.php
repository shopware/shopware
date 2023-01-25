<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\Exception\DisabledUrlUploadFeatureException;
use Shopware\Core\Content\Media\Exception\IllegalUrlException;
use Shopware\Core\Content\Media\Exception\MissingFileExtensionException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('content')]
class FileFetcher
{
    private const ALLOWED_PROTOCOLS = ['http', 'https', 'ftp', 'sftp'];

    /**
     * @internal
     */
    public function __construct(
        private readonly FileUrlValidatorInterface $fileUrlValidator,
        private readonly bool $enableUrlUploadFeature = true,
        private readonly bool $enableUrlValidation = true,
        private readonly int $maxFileSize = 0
    ) {
    }

    public function fetchRequestData(Request $request, string $fileName): MediaFile
    {
        $extension = $this->getExtensionFromRequest($request);
        $expectedLength = (int) $request->headers->get('content-length');

        $inputStream = $request->getContent(true);
        $destStream = $this->openDestinationStream($fileName);

        try {
            $bytesWritten = $this->copyStreams($inputStream, $destStream, 0);
        } finally {
            fclose($inputStream);
            fclose($destStream);
        }

        if ($expectedLength !== $bytesWritten) {
            throw new UploadException('expected content-length did not match actual size');
        }

        return new MediaFile(
            $fileName,
            (string) mime_content_type($fileName),
            $extension,
            $bytesWritten,
            hash_file('md5', $fileName) ?: null
        );
    }

    public function fetchFileFromURL(Request $request, string $fileName): MediaFile
    {
        if (!$this->enableUrlUploadFeature) {
            throw new DisabledUrlUploadFeatureException();
        }

        $url = $this->getUrlFromRequest($request);

        if ($this->enableUrlValidation && !$this->fileUrlValidator->isValid($url)) {
            throw new IllegalUrlException($url);
        }

        $extension = $this->getExtensionFromRequest($request);

        $inputStream = $this->openSourceFromUrl($url);
        $destStream = $this->openDestinationStream($fileName);

        try {
            $writtenBytes = $this->copyStreams($inputStream, $destStream, $this->maxFileSize);
        } finally {
            fclose($inputStream);
            fclose($destStream);
        }

        return new MediaFile(
            $fileName,
            (string) mime_content_type($fileName),
            $extension,
            $writtenBytes,
            hash_file('md5', $fileName) ?: null
        );
    }

    public function fetchBlob(string $blob, string $extension, string $contentType): MediaFile
    {
        $tempFile = (string) tempnam(sys_get_temp_dir(), '');
        $fh = @fopen($tempFile, 'wb');
        \assert($fh !== false);

        $blobSize = (int) @fwrite($fh, $blob);
        $fileHash = $tempFile ? hash_file('md5', $tempFile) : null;

        return new MediaFile(
            $tempFile,
            $contentType,
            $extension,
            $blobSize,
            $fileHash ?: null
        );
    }

    /**
     * @throws MissingFileExtensionException
     */
    private function getExtensionFromRequest(Request $request): string
    {
        $extension = (string) $request->query->get('extension');
        if ($extension === '') {
            throw new MissingFileExtensionException();
        }

        return $extension;
    }

    /**
     * @throws UploadException
     */
    private function getUrlFromRequest(Request $request): string
    {
        $url = (string) $request->request->get('url');

        if ($url === '') {
            throw new UploadException('You must provide a valid url.');
        }

        if (!$this->isUrlValid($url)) {
            throw new UploadException('malformed url: ' . $url);
        }

        return $url;
    }

    /**
     * @throws UploadException
     *
     * @return resource
     */
    private function openSourceFromUrl(string $url)
    {
        $streamContext = stream_context_create([
            'http' => [
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
        ]);

        try {
            $inputStream = @fopen($url, 'rb', false, $streamContext);
        } catch (\Throwable) {
            throw new UploadException("Could not open source stream from {$url}");
        }

        if ($inputStream === false) {
            throw new UploadException("Could not open source stream from {$url}");
        }

        return $inputStream;
    }

    /**
     * @throws UploadException
     *
     * @return resource
     */
    private function openDestinationStream(string $filename)
    {
        try {
            $inputStream = @fopen($filename, 'wb');
        } catch (\Throwable) {
            throw new UploadException("Could not open Stream to write upload data: {$filename}");
        }

        if ($inputStream === false) {
            throw new UploadException("Could not open Stream to write upload data: {$filename}");
        }

        return $inputStream;
    }

    /**
     * @param resource $sourceStream
     * @param resource        $destStream
     */
    private function copyStreams($sourceStream, $destStream, int $maxFileSize = 0): int
    {
        if ($maxFileSize === 0) {
            $writtenBytes = stream_copy_to_stream($sourceStream, $destStream);
            if ($writtenBytes === false) {
                throw new UploadException('Error while copying media from source');
            }

            return $writtenBytes;
        }

        $writtenBytes = stream_copy_to_stream($sourceStream, $destStream, $maxFileSize, 0);
        if ($writtenBytes === false) {
            throw new UploadException('Error while copying media from source');
        }

        if ($writtenBytes === $maxFileSize) {
            throw new UploadException('Source file exceeds maximum file size limit');
        }

        return $writtenBytes;
    }

    private function isUrlValid(string $url): bool
    {
        return (bool) filter_var($url, \FILTER_VALIDATE_URL) && $this->isProtocolAllowed($url);
    }

    private function isProtocolAllowed(string $url): bool
    {
        $fragments = explode(':', $url);
        if (\count($fragments) > 1) {
            return \in_array($fragments[0], self::ALLOWED_PROTOCOLS, true);
        }

        return false;
    }
}
