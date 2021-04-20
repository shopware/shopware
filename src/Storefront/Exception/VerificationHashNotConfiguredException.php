<?php declare(strict_types=1);

namespace Shopware\Storefront\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class VerificationHashNotConfiguredException extends ShopwareHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            'No verification hash configured.',
            [],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__VERIFICATION_HASH_NOT_CONFIGURED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
