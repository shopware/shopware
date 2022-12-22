<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package checkout
 *
 * @deprecated tag:v6.5.0 - Will be removed. Use \Shopware\Core\Checkout\Cart\CartException::orderNotFound instead
 */
class OrderNotFoundException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $orderId;

    public function __construct(string $orderId)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );
        parent::__construct(
            'Order with id "{{ orderId }}" not found.',
            ['orderId' => $orderId]
        );

        $this->orderId = $orderId;
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return 'CHECKOUT__ORDER_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return Response::HTTP_BAD_REQUEST;
    }

    public function getOrderId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->orderId;
    }
}
