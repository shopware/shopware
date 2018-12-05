<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MissingFileException extends ShopwareHttpException
{
    protected $code = 'MISSING_FILE_EXCEPTION';

    public function __construct(string $mediaId, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
            'Could not find file for media with id: "%s"',
            $mediaId
        );
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
