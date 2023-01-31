<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class OrderException extends HttpException
{
    final public const ORDER_MISSING_ORDER_ASSOCIATION_CODE = 'CHECKOUT__ORDER_MISSING_ORDER_ASSOCIATION';
    final public const ORDER_ORDER_DELIVERY_NOT_FOUND_CODE = 'CHECKOUT__ORDER_ORDER_DELIVERY_NOT_FOUND';
    final public const ORDER_ORDER_NOT_FOUND_CODE = 'CHECKOUT__ORDER_ORDER_NOT_FOUND';
    final public const ORDER_MISSING_ORDER_NUMBER_CODE = 'CHECKOUT__ORDER_MISSING_ORDER_NUMBER';
    final public const ORDER_ORDER_TRANSACTION_NOT_FOUND_CODE = 'CHECKOUT__ORDER_ORDER_TRANSACTION_NOT_FOUND';
    final public const ORDER_ORDER_ALREADY_PAID_CODE = 'CHECKOUT__ORDER_ORDER_ALREADY_PAID';
    final public const ORDER_CAN_NOT_RECALCULATE_LIVE_VERSION_CODE = 'CHECKOUT__ORDER_CAN_NOT_RECALCULATE_LIVE_VERSION';
    final public const ORDER_PAYMENT_METHOD_NOT_CHANGEABLE_CODE = 'CHECKOUT__ORDER_PAYMENT_METHOD_NOT_CHANGEABLE';

    public static function missingAssociation(string $association): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ORDER_MISSING_ORDER_ASSOCIATION_CODE,
            'The required association "{{ association }}" is missing .',
            ['association' => $association]
        );
    }

    public static function orderDeliveryNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ORDER_ORDER_DELIVERY_NOT_FOUND_CODE,
            'Order delivery with id {{ id }} not found.',
            ['id' => $id]
        );
    }

    public static function canNotRecalculateLiveVersion(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ORDER_CAN_NOT_RECALCULATE_LIVE_VERSION_CODE,
            'Order with id {{ orderId }} can not be recalculated because it is in the live version. Please create a new version',
            ['orderId' => $orderId]
        );
    }

    public static function orderTransactionNotFound(string $id): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ORDER_ORDER_TRANSACTION_NOT_FOUND_CODE,
            'Order transaction with id {{ id }} not found.',
            ['id' => $id]
        );
    }

    public static function orderAlreadyPaid(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ORDER_ORDER_ALREADY_PAID_CODE,
            'Order with id "{{ orderId }}" was already paid and cannot be edited afterwards.',
            ['orderId' => $orderId]
        );
    }

    public static function paymentMethodNotChangeable(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::ORDER_PAYMENT_METHOD_NOT_CHANGEABLE_CODE,
            'Payment methods of order with current payment transaction type can not be changed.'
        );
    }

    public static function orderNotFound(string $orderId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ORDER_ORDER_NOT_FOUND_CODE,
            'Order with id {{ orderId }} not found.',
            ['orderId' => $orderId]
        );
    }

    public static function missingOrderNumber(string $orderId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ORDER_MISSING_ORDER_NUMBER_CODE,
            'Order with id {{ orderId }} has no order number.',
            ['orderId' => $orderId]
        );
    }
}
