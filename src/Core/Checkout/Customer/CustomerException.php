<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class CustomerException extends HttpException
{
    public const CUSTOMER_NOT_FOUND = 'CHECKOUT__CUSTOMER_NOT_FOUND';
    public const CUSTOMER_GROUP_NOT_FOUND = 'CHECKOUT__CUSTOMER_GROUP_NOT_FOUND';
    public const CUSTOMER_GROUP_REQUEST_NOT_FOUND = 'CHECKOUT__CUSTOMER_GROUP_REQUEST_NOT_FOUND';
    public const CUSTOMER_NOT_LOGGED_IN = 'CHECKOUT__CUSTOMER_NOT_LOGGED_IN';
    public const LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND = 'CHECKOUT__LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND';
    public const CUSTOMER_IDS_PARAMETER_IS_MISSING = 'CHECKOUT__CUSTOMER_IDS_PARAMETER_IS_MISSING';

    public static function customerGroupNotFound(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_GROUP_NOT_FOUND,
            'Customer group with id "{{ id }}" not found',
            ['id' => $id]
        );
    }

    public static function groupRequestNotFound(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_GROUP_REQUEST_NOT_FOUND,
            'Group request for customer "{{ id }}" is not found',
            ['id' => $id]
        );
    }

    /**
     * @param string[] $ids
     */
    public static function customersNotFound(array $ids): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CUSTOMER_NOT_FOUND,
            'These customers "{{ ids }}" are not found',
            ['ids' => implode(', ', $ids)]
        );
    }

    public static function customerNotLoggedIn(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::CUSTOMER_NOT_LOGGED_IN,
            'Customer is not logged in.',
        );
    }

    public static function downloadFileNotFound(string $downloadId): ShopwareHttpException
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::LINE_ITEM_DOWNLOAD_FILE_NOT_FOUND,
            'Line item download file with id "{{ downloadId }}" not found.',
            ['downloadId' => $downloadId]
        );
    }

    public static function customerIdsParameterIsMissing(): ShopwareHttpException
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_IDS_PARAMETER_IS_MISSING,
            'Parameter "customerIds" is missing.',
        );
    }
}
