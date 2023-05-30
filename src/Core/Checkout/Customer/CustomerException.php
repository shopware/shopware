<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByIdException;
use Shopware\Core\Checkout\Customer\Exception\InvalidLoginAsCustomerTokenException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class CustomerException extends HttpException
{
    public const CUSTOMER_GROUP_NOT_FOUND = 'CHECKOUT__CUSTOMER_GROUP_NOT_FOUND';

    public const LOGIN_AS_CUSTOMER_INVALID_TOKEN_CODE = 'CHECKOUT__LOGIN_AS_CUSTOMER_INVALID_TOKEN';
    public const LOGIN_AS_CUSTOMER_MISSING_CUSTOMER_ID_CODE = 'CHECKOUT__LOGIN_AS_CUSTOMER_MISSING_CUSTOMER_ID';
    public const LOGIN_AS_CUSTOMER_MISSING_SALES_CHANNEL_ID_CODE = 'CHECKOUT__LOGIN_AS_CUSTOMER_MISSING_SALES_CHANNEL_ID';
    public const LOGIN_AS_CUSTOMER_MISSING_TOKEN_CODE = 'CHECKOUT__LOGIN_AS_CUSTOMER_MISSING_TOKEN';

    public static function customerGroupNotFound(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_GROUP_NOT_FOUND,
            'Customer group with id "{{ id }}" not found',
            ['id' => $id]
        );
    }

    public static function invalidToken(string $token): InvalidLoginAsCustomerTokenException
    {
        return new InvalidLoginAsCustomerTokenException(
            Response::HTTP_BAD_REQUEST,
            self::LOGIN_AS_CUSTOMER_INVALID_TOKEN_CODE,
            'The token "{{ token }}" is invalid.',
            ['token' => $token]
        );
    }

    public static function missingCustomerId(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LOGIN_AS_CUSTOMER_MISSING_CUSTOMER_ID_CODE,
            'customerId is missing.',
        );
    }

    public static function missingSalesChannelId(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LOGIN_AS_CUSTOMER_MISSING_SALES_CHANNEL_ID_CODE,
            'salesChannelId is missing.',
        );
    }

    public static function missingToken(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::LOGIN_AS_CUSTOMER_MISSING_TOKEN_CODE,
            'token is missing.',
        );
    }

    public static function customerNotFoundById(string $customerId): CustomerNotFoundByIdException
    {
        return new CustomerNotFoundByIdException($customerId);
    }
}
