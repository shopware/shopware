<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MissingLineItemPriceException extends ShopwareHttpException
{
    protected $code = 'CART-MISSING-PRICE-DEFINITION';

    public function __construct(string $identifier, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Line item %s contains no price definition or already calculated price', $identifier);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
