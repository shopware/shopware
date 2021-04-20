<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidSalesChannelIdException extends ShopwareHttpException
{
    public function __construct(string $salesChannelId, ?\Throwable $previous = null)
    {
        parent::__construct(
            'The provided salesChannelId "{{ salesChannelId }}" is invalid.',
            ['salesChannelId' => $salesChannelId],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_SALES_CHANNEL';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
