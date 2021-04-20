<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class FileTypeNotAllowedException extends ShopwareHttpException
{
    public function __construct(string $mimeType, string $uploadType, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Type "{{ mimeType }}" of provided file is not allowed for {{ uploadType }}',
            ['mimeType' => $mimeType, 'uploadType' => $uploadType],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'STOREFRONT__MEDIA_ILLEGAL_FILE_TYPE';
    }
}
