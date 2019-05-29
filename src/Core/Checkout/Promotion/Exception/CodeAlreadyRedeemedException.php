<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CodeAlreadyRedeemedException extends ShopwareHttpException
{
    public function __construct(string $code)
    {
        parent::__construct('Promotion with code "{{ code }}" has already been marked as redeemed!', ['code' => $code]);
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CODE_ALREADY_REDEEMED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
