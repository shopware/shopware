<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class FileTypeNotSupportedException extends ShopwareHttpException
{
    /**
     * @deprecated tag:v6.5.0 - Parameter extension will be mandatory
     */
    public function __construct(string $mediaId, string $extension = '')
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            'parameter extension will be mandatory'
        );
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
