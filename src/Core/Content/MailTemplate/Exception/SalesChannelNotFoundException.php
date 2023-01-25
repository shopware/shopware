<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('sales-channel')]
class SalesChannelNotFoundException extends ShopwareHttpException
{
    public function __construct(string $salesChannelId)
    {
        parent::__construct(
            'Sales channel with id "{{ salesChannelId }}" was not found.',
            ['salesChannelId' => $salesChannelId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__SALES_CHANNEL_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
