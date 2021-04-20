<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MediaFolderNotFoundException extends ShopwareHttpException
{
    public function __construct(string $folderId, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Could not find media folder with id: "{{ folderId }}"',
            ['folderId' => $folderId],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_FOLDER_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
