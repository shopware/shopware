<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Exception\CountryNotFoundException;
use Shopware\Core\System\Country\Exception\CountryStateNotFoundException;
use Symfony\Component\HttpFoundation\Response;

#[Package('buyers-experience')]
class CountryException extends HttpException
{
    public const COUNTRY_NOT_FOUND = 'CHECKOUT__COUNTRY_NOT_FOUND';
    public const COUNTRY_STATE_NOT_FOUND = 'CHECKOUT__COUNTRY_STATE_NOT_FOUND';

    public static function countryNotFound(string $id): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new CountryNotFoundException($id);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COUNTRY_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'country', 'field' => 'id', 'value' => $id]
        );
    }

    public static function countryStateNotFound(string $id): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new CountryStateNotFoundException($id);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COUNTRY_STATE_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'country state', 'field' => 'id', 'value' => $id]
        );
    }
}
