<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class SalesChannelNotFoundException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('No matching sales channel found.');
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__ROUTING_SALES_CHANNEL_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_PRECONDITION_FAILED;
    }
}
