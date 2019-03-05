<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelNotFoundException extends ShopwareHttpException
{
    protected $code = 'SALES-CHANNEL-NOT-FOUND';

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
