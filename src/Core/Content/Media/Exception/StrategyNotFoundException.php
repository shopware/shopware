<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('content')]
class StrategyNotFoundException extends ShopwareHttpException
{
    public function __construct(string $strategyName)
    {
        parent::__construct(
            'No Strategy with name "{{ strategyName }}" found.',
            ['strategyName' => $strategyName]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_STRATEGY_NOT_FOUND';
    }
}
