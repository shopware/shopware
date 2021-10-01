<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LanguageOfSalesChannelDomainDeleteException extends ShopwareHttpException
{
    /**
     * @deprecated tag:v6.5.0 - $language parameter will be removed
     */
    public function __construct(string $language, $e)
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
