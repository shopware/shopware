<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('sales-channel')]
class LanguageOfSalesChannelDomainDeleteException extends ShopwareHttpException
{
    public function __construct(?\Throwable $e = null)
    {
        parent::__construct(
            'The language cannot be deleted because saleschannel domains with this language exist.',
            [],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__LANGUAGE_OF_SALES_CHANNEL_DOMAIN_DELETE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
