<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\Exception\UploadException;
use Symfony\Component\HttpFoundation\Request;

class FileFetcher
{
    public function fetchRequestData(Request $request, MediaFile $mediaFile): void
    {
        $inputStream = $request->getContent(true);
        $destStream = $this->openDestinationStream($mediaFile->getFileName());

        try {
            $bytesWritten = $this->copyStreams($inputStream, $destStream);
        } finally {
            fclose($inputStream);
            fclose($destStream);
        }

        if ($mediaFile->getFileSize() !== $bytesWritten) {
            throw new UploadException('expected content-length did not match actual size');
        }
    }

    public function fetchFileFromURL(MediaFile $mediaFile, string $url): MediaFile
    {
        if (!$this->isUrlValid($url)) {
            throw new UploadException('malformed url: ' . $url);
        }

        $inputStream = $this->openSourceFromUrl($url);
        $destStream = $this->openDestinationStream($mediaFile->getFileName());

        try {
            $writtenBytes = $this->copyStreams($inputStream, $destStream);
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
    private function openSourceFromUrl(string $url)
    {
        $inputStream = @fopen($url, 'r');

        if ($inputStream === false) {
            throw new UploadException("Could open source stream from {$url}");
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
        $inputStream = @fopen($filename, 'w');

        if ($inputStream === false) {
            throw new UploadException('Could not open Stream to write uploaddata: {filename}');
        }

        return $inputStream;
    }

    private function copyStreams($sourceStream, $destStream): int
    {
        $writtenBytes = stream_copy_to_stream($sourceStream, $destStream);

        if ($writtenBytes === false) {
            throw new UploadException('Error while copying media from source');
        }

        return $writtenBytes;
    }

    private function isUrlValid(string $url): bool
    {
        return (bool) filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:/', $url);
    }
}
