<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Throwable;

class CouldNotRenameFileException extends ShopwareHttpException
{
    protected $code = 'COULD_NOT_RENAME_FILE_EXCEPTION';

    public function __construct(string $mediaId, string $oldFileName, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
            'Could not rename File for media with id: %s. Rollback to filename: "%s"',
            $mediaId,
            $oldFileName
        );
        parent::__construct($message, $code, $previous);
    }
}
