<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * @package content
 */
class FileExtensionNotSupportedException extends FileTypeNotSupportedException
{
    public function __construct(string $mediaId, string $extension)
    {
        parent::__construct(
            'The file extension "{{ extension }}" for media object with id {{ mediaId }} is not supported.',
            ['mediaId' => $mediaId, 'extension' => $extension]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_FILE_TYPE_NOT_SUPPORTED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
