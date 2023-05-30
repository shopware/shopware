<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:becomes-internal - Use PromotionException::codeAlreadyRedeemed instead
 */
#[Package('checkout')]
class CodeAlreadyRedeemedException extends ShopwareHttpException
{
    public function __construct(string $code)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use PromotionException::codeAlreadyRedeemed instead')
        );

        parent::__construct('Promotion with code "{{ code }}" has already been marked as redeemed!', ['code' => $code]);
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use PromotionException::codeAlreadyRedeemed instead')
        );

        return 'CHECKOUT__CODE_ALREADY_REDEEMED';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'Use PromotionException::codeAlreadyRedeemed instead')
        );

        return Response::HTTP_BAD_REQUEST;
    }
}
