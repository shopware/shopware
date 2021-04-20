<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StrategyNotFoundException extends ShopwareHttpException
{
    public function __construct(string $strategyName, ?\Throwable $previous = null)
    {
        parent::__construct(
            'No Strategy with name "{{ strategyName }}" found.',
            ['strategyName' => $strategyName],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_STRATEGY_NOT_FOUND';
    }
}
