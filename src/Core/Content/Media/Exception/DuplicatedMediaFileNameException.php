<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class DuplicatedMediaFileNameException extends ShopwareHttpException
{
    protected $code = 'DUPLICATED_MEDIA_FILE_NAME_EXCEPTION';

    public function __construct(string $fileName, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf(
            'A file with the name "%s" already exists',
            $fileName
        );
        parent::__construct($message, $code, $previous);
    }
}
