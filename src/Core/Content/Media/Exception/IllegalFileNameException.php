<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class IllegalFileNameException extends ShopwareHttpException
{
    protected $code = 'ILLEGAL_FILE_NAME_EXCEPTION';

    public function __construct(string $filename, string $cause, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf(
            'Provided filename "%s" ist not permitted: %s',
            $filename,
            $cause
        );
        parent::__construct($message, $code, $previous);
    }
}
