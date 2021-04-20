<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CustomerNotFoundByHashException extends ShopwareHttpException
{
    public function __construct(string $hash, ?\Throwable $previous = null)
    {
        parent::__construct(
            'No matching customer for the hash "{{ hash }}" was found.',
            ['hash' => $hash],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_NOT_FOUND_BY_HASH';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
