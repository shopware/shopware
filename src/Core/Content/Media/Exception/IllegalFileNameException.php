<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class IllegalFileNameException extends ShopwareHttpException
{
    public function __construct(string $filename, string $cause)
    {
        parent::__construct(
            'Provided filename "{{ fileName }}" ist not permitted: {{ cause }}',
            ['fileName' => $filename, 'cause' => $cause]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_ILLEGAL_FILE_NAME';
    }
}
