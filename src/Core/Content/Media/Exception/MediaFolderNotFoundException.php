<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('content')]
class MediaFolderNotFoundException extends ShopwareHttpException
{
    public function __construct(string $folderId)
    {
        parent::__construct(
            'Could not find media folder with id: "{{ folderId }}"',
            ['folderId' => $folderId]
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
