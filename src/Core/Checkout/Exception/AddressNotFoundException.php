<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class AddressNotFoundException extends ShopwareHttpException
{
    protected $code = 'ADDRESS-NOT-FOUND';

    public function __construct(string $id, $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('Customer address with id %s not found', $id);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
