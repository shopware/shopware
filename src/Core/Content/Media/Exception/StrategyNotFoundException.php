<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::strategyNotFound instead
 */
#[Package('content')]
class StrategyNotFoundException extends ShopwareHttpException
{
    public function __construct(string $strategyName)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::strategyNotFound instead')
        );

        parent::__construct(
            'No Strategy with name "{{ strategyName }}" found.',
            ['strategyName' => $strategyName]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::strategyNotFound instead')
        );

        return 'CONTENT__MEDIA_STRATEGY_NOT_FOUND';
    }
}
