<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CustomerNotFoundException extends ShopwareHttpException
{
    protected $code = 'CUSTOMER-NOT_FOUND';

    public function __construct(string $email, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('No matching customer for email "%s" was found.', $email);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
