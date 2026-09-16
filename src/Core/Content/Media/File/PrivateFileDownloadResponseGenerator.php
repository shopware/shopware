<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Psr\Http\Message\StreamInterface;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 */
#[Package('discovery')]
final class PrivateFileDownloadResponseGenerator
{
    /**
     * @param \Closure(): StreamInterface $streamProvider
     * @param array<string, mixed> $headers
     */
    public function createResponse(
        \Closure $streamProvider,
        array $headers,
        string $downloadStrategy,
        string $path,
        string $pathPrefix = ''
    ): Response {
        if ($downloadStrategy === DownloadResponseGenerator::X_ACCEL_DOWNLOAD_STRATEGY) {
            $location = $path;

            if ($pathPrefix !== '') {
                $location = $pathPrefix . '/' . ltrim($location, '/');
            }

            $response = new Response(null, Response::HTTP_OK, $headers);
            $response->headers->set(DownloadResponseGenerator::X_ACCEL_REDIRECT, $location);

            return $response;
        }

        $stream = $streamProvider();
        $resource = $stream->detach();

        if (!\is_resource($resource)) {
            throw MediaException::fileNotFound($path);
        }

        $metadata = stream_get_meta_data($resource);

        if ($downloadStrategy === DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY
            && ($metadata['wrapper_type'] ?? null) === 'plainfile'
            && isset($metadata['uri'])
        ) {
            $location = $metadata['uri'];

            $response = new Response(null, Response::HTTP_OK, $headers);
            $response->headers->set(DownloadResponseGenerator::X_SENDFILE_DOWNLOAD_STRATEGY, $location);

            return $response;
        }

        return new StreamedResponse(static function () use ($resource): void {
            fpassthru($resource);
        }, Response::HTTP_OK, $headers);
    }
}
