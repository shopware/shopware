<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class FileTypeNotSupportedException extends ShopwareHttpException
{
    protected $code = 'FILE_TYPE_NOT_SUPPORTED_EXCEPTION';

    public function __construct(string $mediaId, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('The File for media object with id: %s is not supported for creating thumbnails.', $mediaId);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
