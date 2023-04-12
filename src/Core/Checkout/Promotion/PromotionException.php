<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class PromotionException extends HttpException
{
    public const PROMOTION_CODE_ALREADY_REDEEMED = 'CHECKOUT__CODE_ALREADY_REDEEMED';

    public static function codeAlreadyRedeemed(string $code): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PROMOTION_CODE_ALREADY_REDEEMED,
            'Promotion with code "{{ code }}" has already been marked as redeemed!',
            ['code' => $code]
        );
    }
}
