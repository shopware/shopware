<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelMissingEmail extends ShopwareHttpException
{
    public function __construct(string $salesChannelId)
    {
        parent::__construct(
            'Sales channel with id "{{ salesChannelId }}" ist missing a sender email address.',
            ['salesChannelId' => $salesChannelId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__SALES_CHANNEL_MISSING_SENDER_EMAIL';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
