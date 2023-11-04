<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('content')]
class IllegalFileNameException extends ShopwareHttpException
{
    public function __construct(
        string $filename,
        string $cause
    ) {
        parent::__construct(
            'Provided filename "{{ fileName }}" is not permitted: {{ cause }}',
            ['fileName' => $filename, 'cause' => $cause]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_ILLEGAL_FILE_NAME';
    }
}
