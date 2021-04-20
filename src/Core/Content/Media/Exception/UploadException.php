<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class UploadException extends ShopwareHttpException
{
    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct('{{ message }}', ['message' => $message], $previous);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_UPLOAD';
    }
}
