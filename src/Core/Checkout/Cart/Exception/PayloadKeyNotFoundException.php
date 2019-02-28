<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class PayloadKeyNotFoundException extends ShopwareHttpException
{
    protected $code = 'PAYLOAD-KEY-NOT-FOUND';

    public function __construct(string $key, string $lineItemId, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('Payload key `%s` in line item `%s` not found', $key, $lineItemId);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
