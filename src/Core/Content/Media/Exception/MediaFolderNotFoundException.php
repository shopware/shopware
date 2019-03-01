<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MediaFolderNotFoundException extends ShopwareHttpException
{
    protected $code = 'MEDIA_FOLDER_NOT_FOUND_EXCEPTION';

    public function __construct(string $folderId, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf(
            'Could not find media folder with id: "%s"',
            $folderId
        );
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
