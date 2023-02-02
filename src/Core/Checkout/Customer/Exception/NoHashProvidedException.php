<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class NoHashProvidedException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'The given hash is empty.'
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__NO_HASH_PROVIDED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
