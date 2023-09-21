<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:remove-exception - will be removed, use CountryException::countryNotFound instead
 */
#[Package('buyers-experience')]
class CountryNotFoundException extends CountryException
{
    public function __construct(string $id)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::COUNTRY_NOT_FOUND,
            'Country with id "{{ countryId }}" not found.',
            ['countryId' => $id]
        );
    }
}
