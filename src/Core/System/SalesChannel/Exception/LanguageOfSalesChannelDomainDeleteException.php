<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LanguageOfSalesChannelDomainDeleteException extends ShopwareHttpException
{
    public function __construct(string $language, $e)
    {
        parent::__construct(
            'The language "{{ language }}" cannot be deleted because saleschannel domains with this language exist.',
            ['language' => $language],
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
