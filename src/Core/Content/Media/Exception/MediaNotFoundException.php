<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MediaNotFoundException extends ShopwareHttpException
{
    public const CODE = 600000;

    public function __construct(string $mediaId, int $code = self::CODE, ?\Throwable $previous = null)
    {
        $message = sprintf('Media for id %s not found', $mediaId);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
