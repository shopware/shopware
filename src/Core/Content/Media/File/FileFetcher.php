<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\Exception\UploadException;
use Symfony\Component\HttpFoundation\Request;

class FileFetcher
{
    public function fetchRequestData(Request $request, MediaFile $mediaFile): void
    {
        $inputStream = $request->getContent(true);
        $destStream = $this->openStream($mediaFile->getFileName(), 'w');

        try {
            $this->copyStreams($mediaFile->getFileSize(), $inputStream, $destStream);
        } finally {
            fclose($inputStream);
            fclose($destStream);
        }
    }

    public function fetchFileFromURL(MediaFile $mediaFile, string $url): MediaFile
    {
        if (!$this->isUrlValid($url)) {
            throw new UploadException('malformed url: ' . $url);
        }

        $inputStream = $this->openStream($url, 'r');
        $destStream = $this->openStream($mediaFile->getFileName(), 'w');

        try {
            $writtenBytes = stream_copy_to_stream($inputStream, $destStream);
        } finally {
            fclose($inputStream);
            fclose($destStream);
        }

        return new MediaFile(
            $mediaFile->getFileName(),
            mime_content_type($mediaFile->getFileName()),
            $mediaFile->getFileExtension(),
            $writtenBytes
        );
    }

    /**
     * @throws UploadException
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

    private function copyStreams(int $length, $inputStream, $tempStream): void
    {
        $bytesWritten = stream_copy_to_stream($inputStream, $tempStream);

        if ($bytesWritten !== $length) {
            throw new UploadException('expected content-length did not match actual size');
        }
    }

    private function isUrlValid(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:/', $url);
    }
}
