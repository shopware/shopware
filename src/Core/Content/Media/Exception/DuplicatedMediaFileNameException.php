<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('content')]
class DuplicatedMediaFileNameException extends ShopwareHttpException
{
    public function __construct(
        string $fileName,
        string $fileExtension
    ) {
        parent::__construct(
            'A file with the name "{{ fileName }}.{{ fileExtension }}" already exists.',
            ['fileName' => $fileName, 'fileExtension' => $fileExtension]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_DUPLICATED_FILE_NAME';
    }
}
