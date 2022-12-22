<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package checkout
 *
 * @deprecated tag:v6.5.0 - Will be removed. Use \Shopware\Core\Checkout\Cart\CartException::orderDeliveryNotFound instead
 */
class OrderDeliveryNotFoundException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $orderDeliveryId;

    public function __construct(string $orderDeliveryId)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );
        parent::__construct(
            'Order delivery with id "{{ orderDeliveryId }}" not found.',
            ['orderDeliveryId' => $orderDeliveryId]
        );

        $this->orderDeliveryId = $orderDeliveryId;
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return 'CHECKOUT__ORDER_DELIVERY_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return Response::HTTP_BAD_REQUEST;
    }

    public function getOrderDeliveryId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->orderDeliveryId;
    }
}
