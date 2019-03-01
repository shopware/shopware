<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidSalesChannelIdException extends ShopwareHttpException
{
    public $code = 'INVALID-SALES-CHANNEL-ID';

    public function __construct(string $touchpointId, $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('The provided salesChannelId `%s` is invalid.', $touchpointId);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
