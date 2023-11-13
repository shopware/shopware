<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('buyers-experience')]
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
            throw MediaException::invalidContentLength();
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
            throw MediaException::disableUrlUploadFeature();
        }

        $url = $this->getUrlFromRequest($request);

        if ($this->enableUrlValidation && !$this->fileUrlValidator->isValid($url)) {
            throw MediaException::illegalUrl($url);
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
     * @throws MediaException
     */
    private function getExtensionFromRequest(Request $request): string
    {
        $extension = (string) $request->query->get('extension');
        if ($extension === '') {
            throw MediaException::missingFileExtension();
        }

        return $extension;
    }

    /**
     * @throws MediaException
     */
    private function getUrlFromRequest(Request $request): string
    {
        $url = (string) $request->request->get('url');

        if ($url === '') {
            throw MediaException::missingUrlParameter();
        }

        if (!$this->isUrlValid($url)) {
            throw MediaException::invalidUrl($url);
        }

        return $url;
    }

    /**
     * @throws MediaException
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
            throw MediaException::cannotOpenSourceStreamToRead($url);
        }

        if ($inputStream === false) {
            throw MediaException::cannotOpenSourceStreamToRead($url);
        }

        return $inputStream;
    }

    /**
     * @throws MediaException
     *
     * @return resource
     */
    private function openDestinationStream(string $filename)
    {
        try {
            $inputStream = @fopen($filename, 'wb');
        } catch (\Throwable) {
            throw MediaException::cannotOpenSourceStreamToWrite($filename);
        }

        if ($inputStream === false) {
            throw MediaException::cannotOpenSourceStreamToWrite($filename);
        }

        return $inputStream;
    }

    /**
     * @param resource $sourceStream
     * @param resource $destStream
     */
    private function copyStreams($sourceStream, $destStream, int $maxFileSize = 0): int
    {
        if ($maxFileSize === 0) {
            $writtenBytes = stream_copy_to_stream($sourceStream, $destStream);
            if ($writtenBytes === false) {
                throw MediaException::cannotCopyMedia();
            }

            return $writtenBytes;
        }

        $writtenBytes = stream_copy_to_stream($sourceStream, $destStream, $maxFileSize, 0);
        if ($writtenBytes === false) {
            throw MediaException::cannotCopyMedia();
        }

        if ($writtenBytes === $maxFileSize) {
            throw MediaException::fileSizeLimitExceeded();
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
