<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class EmptyMediaFilenameException extends ShopwareHttpException
{
    protected $code = 'EMPTY_MEDIA_FILE_EXCEPTION';

    public function __construct(int $code = 0, \Throwable $previous = null)
    {
        parent::__construct('A valid Filename must be provided', $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
