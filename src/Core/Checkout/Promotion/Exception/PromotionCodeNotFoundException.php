<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class PromotionCodeNotFoundException extends ShopwareHttpException
{
    public function __construct(string $code)
    {
        parent::__construct('Promotion Code "{{ code }}" has not been found!', ['code' => $code]);
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CODE_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
