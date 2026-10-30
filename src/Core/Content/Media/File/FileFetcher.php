<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Package('discovery')]
class FileFetcher
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FileUrlValidatorInterface $fileUrlValidator,
        private readonly FileService $fileService,
        private readonly TrustedUrlResolver $trustedUrlResolver,
        private readonly HttpClientInterface $httpClient,
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
            $bytesWritten = $this->copyStreams($inputStream, $destStream);
        } finally {
            fclose($inputStream);
            fclose($destStream);
        }

        if ($expectedLength !== $bytesWritten) {
            throw MediaException::invalidContentLength();
        }

        return new MediaFile(
            $fileName,
            FileInfoHelper::getMimeType($fileName, $extension),
            $extension,
            $bytesWritten,
            // Change length of db field `media`.`file_hash` if algorithm is changed
            Hasher::hashFile($fileName, 'md5')
        );
    }

    public function fetchFileFromURL(Request $request, string $fileName): MediaFile
    {
        if (!$this->enableUrlUploadFeature) {
            throw MediaException::disableUrlUploadFeature();
        }

        $url = $this->getUrlFromRequest($request);

        if (!$this->fileService->isUrl($url)) {
            throw MediaException::invalidUrl($url);
        }

        if ($this->enableUrlValidation && !$this->fileUrlValidator->isValid($url)) {
            throw MediaException::illegalUrl($url);
        }

        $writtenBytes = $this->downloadToFile($url, $fileName);

        $extension = (string) $request->query->get('extension');
        $mimeType = FileInfoHelper::getMimeType($fileName, $extension);
        $extension = $extension ?: FileInfoHelper::getExtension($mimeType);

        return new MediaFile(
            $fileName,
            $mimeType,
            $extension,
            $writtenBytes,
            // Change length of db field `media`.`file_hash` if algorithm is changed
            Hasher::hashFile($fileName, 'md5')
        );
    }

    public function fetchBlob(string $blob, string $extension, string $contentType): MediaFile
    {
        $tempFile = (string) tempnam(sys_get_temp_dir(), '');
        $fh = @fopen($tempFile, 'w');
        \assert($fh !== false);

        $blobSize = (int) @fwrite($fh, $blob);
        $fileHash = $tempFile ? Hasher::hashFile($tempFile, 'md5') : null;

        return new MediaFile(
            $tempFile,
            $contentType,
            $extension,
            $blobSize,
            $fileHash
        );
    }

    public function cleanUpTempFile(MediaFile $mediaFile): void
    {
        if ($mediaFile->getFileName() !== '') {
            unlink($mediaFile->getFileName());
        }
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

        return $url;
    }

    /**
     * @throws MediaException
     */
    private function downloadToFile(string $url, string $fileName): int
    {
        $resolved = $this->trustedUrlResolver->resolve($url);

        if (strtolower((string) parse_url($url, \PHP_URL_SCHEME)) === 'ftp') {
            return $this->downloadWithCurl($url, $fileName, $resolved);
        }

        $client = $this->httpClient;
        $options = [
            'max_redirects' => 0,
            'headers' => ['User-Agent' => 'Shopware Remote File Fetcher'],
            'resolve' => [$resolved->host => $resolved->ip],
        ];

        if ($this->enableUrlValidation && !$this->trustedUrlResolver->isAllowedPrivateIp($resolved->ip)) {
            $client = new NoPrivateNetworkHttpClient($client, TrustedUrlResolver::BLOCKED_SUBNETS);
        }

        $destStream = $this->openDestinationStream($fileName);
        $writtenBytes = 0;

        try {
            $response = $client->request('GET', $url, $options);

            foreach ($client->stream($response) as $chunk) {
                $content = $chunk->getContent();
                if ($content === '') {
                    continue;
                }

                $bytes = @fwrite($destStream, $content);
                if ($bytes === false) {
                    throw MediaException::cannotCopyMedia();
                }
                $writtenBytes += $bytes;

                if ($this->maxFileSize > 0 && $writtenBytes >= $this->maxFileSize) {
                    throw MediaException::fileSizeLimitExceeded();
                }
            }
        } catch (HttpClientExceptionInterface) {
            throw MediaException::cannotOpenSourceStreamToRead($url);
        } finally {
            fclose($destStream);
        }

        return $writtenBytes;
    }

    /**
     * @throws MediaException
     */
    private function downloadWithCurl(string $url, string $fileName, ResolvedUrl $resolved): int
    {
        // CURLOPT_CONNECT_TO separates fields by ":", so IPv6 needs brackets
        $ip = str_contains($resolved->ip, ':') ? '[' . $resolved->ip . ']' : $resolved->ip;
        $destStream = $this->openDestinationStream($fileName);
        $writtenBytes = 0;
        $error = null;

        $handle = curl_init($url);
        // curl_setopt_array() stops at the first option it fails to set
        $optionsSet = curl_setopt_array($handle, [
            // CURLOPT_PROTOCOLS_STR needs PHP 8.3
            \CURLOPT_PROTOCOLS => \CURLPROTO_FTP,
            \CURLOPT_CONNECT_TO => ['::' . $ip . ':'],
            // a proxy from the environment would bypass the pinned address
            \CURLOPT_PROXY => '',
            \CURLOPT_FTP_USE_EPSV => true,
            \CURLOPT_FTP_SKIP_PASV_IP => true,
            // abort stalled transfers like HttpClient's idle timeout
            \CURLOPT_LOW_SPEED_LIMIT => 1,
            \CURLOPT_LOW_SPEED_TIME => max(0, (int) \ini_get('default_socket_timeout')),
            \CURLOPT_WRITEFUNCTION => function (\CurlHandle $handle, string $data) use ($destStream, &$writtenBytes, &$error): int {
                $bytes = @fwrite($destStream, $data);
                if ($bytes === false) {
                    $error = MediaException::cannotCopyMedia();

                    return 0;
                }
                $writtenBytes += $bytes;

                if ($this->maxFileSize > 0 && $writtenBytes >= $this->maxFileSize) {
                    $error = MediaException::fileSizeLimitExceeded();

                    return 0;
                }

                return $bytes;
            },
        ]);

        if (!$optionsSet) {
            fclose($destStream);

            throw MediaException::cannotOpenSourceStreamToRead($url);
        }

        try {
            $success = curl_exec($handle);
        } finally {
            fclose($destStream);
        }

        if ($error !== null) {
            throw $error;
        }

        if ($success !== true || curl_getinfo($handle, \CURLINFO_PRIMARY_IP) !== $resolved->ip) {
            throw MediaException::cannotOpenSourceStreamToRead($url);
        }

        return $writtenBytes;
    }

    /**
     * @throws MediaException
     *
     * @return resource
     */
    private function openDestinationStream(string $filename)
    {
        try {
            $inputStream = @fopen($filename, 'w');
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
    private function copyStreams($sourceStream, $destStream): int
    {
        $writtenBytes = stream_copy_to_stream($sourceStream, $destStream);
        if ($writtenBytes === false) {
            throw MediaException::cannotCopyMedia();
        }

        return $writtenBytes;
    }
}
