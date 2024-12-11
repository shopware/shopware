<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed. Use PromotionException::promotionSetGroupNotFound instead
 */
#[Package('buyers-experience')]
class SetGroupNotFoundException extends ShopwareHttpException
{
    public function __construct(string $groupId)
    {
        parent::__construct('Promotion SetGroup "{{ id }}" has not been found!', ['id' => $groupId]);
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'PromotionException::promotionSetGroupNotFound')
        );

        return 'CHECKOUT__PROMOTION_SETGROUP_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'PromotionException::promotionSetGroupNotFound')
        );

        return Response::HTTP_BAD_REQUEST;
    }
}
