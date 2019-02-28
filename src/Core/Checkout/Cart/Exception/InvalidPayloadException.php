<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidPayloadException extends ShopwareHttpException
{
    protected $code = 'INVALID-PAYLOAD';

    public function __construct(string $key, string $lineItemId, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf(
            'Unable to save payload with key `%s` on line item `%s`. 
            Only scalar data types with a max length of %s are allowed.',
            $key,
            $lineItemId,
            LineItem::PAYLOAD_LIMIT
        );

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
