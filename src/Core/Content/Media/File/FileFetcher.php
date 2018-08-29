<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\Exception\MimeTypeMismatchException;
use Shopware\Core\Content\Media\Exception\UploadException;
use Symfony\Component\HttpFoundation\Request;

class FileFetcher
{
    /**
     * @param Request $request
     * @param string  $destination
     * @param string  $mimeType
     * @param int     $length
     *
     * @throws MimeTypeMismatchException
     */
    public function fetchRequestData(Request $request, string $destination, string $mimeType, int $length): void
    {
        $inputStream = $request->getContent(true);
        $destStream = $this->openStream($destination, 'w');

        try {
            $this->copyStreams($length, $inputStream, $destStream);
        } finally {
            fclose($inputStream);
            fclose($destStream);
        }

        $fileType = mime_content_type($destination);
        if ($fileType != $mimeType) {
            throw new MimeTypeMismatchException($mimeType, $fileType);
        }
    }

    /**
     * @param string $destination
     * @param string $url
     *
     * @return int
     */
    public function fetchFileFromURL(string $destination, string $url): int
    {
        if (!$this->isUrlValid($url)) {
            throw new UploadException('malformed url: ' . $url);
        }

        $inputStream = $this->openStream($url, 'r');
        $destStream = $this->openStream($destination, 'w');

        try {
            $writtenBytes = stream_copy_to_stream($inputStream, $destStream);
        } finally {
            fclose($inputStream);
            fclose($destStream);
        }

        return $writtenBytes;
    }

    /**
     * @param string $source
     * @param string $mode
     *
     * @return resource
     */
    private function openStream(string $source, string $mode)
    {
        $inputStream = fopen($source, $mode);

        if (!$inputStream) {
            throw new UploadException("could not open stream from {$source}");
        }

        return $inputStream;
    }

    /**
     * @param int      $length
     * @param resource $inputStream
     * @param resource $tempStream
     */
    private function copyStreams(int $length, $inputStream, $tempStream): void
    {
        $bytesWritten = stream_copy_to_stream($inputStream, $tempStream);

        if ($bytesWritten !== $length) {
            throw new UploadException('expected content-length did not match actual size');
        }
    }

    /**
     * @param string $url
     *
     * @return bool
     */
    private function isUrlValid(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:/', $url);
    }
}
