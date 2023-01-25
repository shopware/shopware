<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('system-settings')]
class CountryNotFoundException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        parent::__construct(
            'Country with id "{{ countryId }}" not found.',
            ['countryId' => $id]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__COUNTRY_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
