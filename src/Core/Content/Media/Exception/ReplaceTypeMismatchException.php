<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Throwable;

class ReplaceTypeMismatchException extends \Exception
{
    public function __construct(string $type, $code = 0, Throwable $previous = null)
    {
        $message = sprintf('To replace the media file, an "%s" file is required.', $type);

        parent::__construct($message, $code, $previous);
    }
}
