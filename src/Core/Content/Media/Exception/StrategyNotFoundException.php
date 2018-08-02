<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class StrategyNotFoundException extends ShopwareHttpException
{
    protected $code = 'STRATEGY_NOT_FOUND';

    public function __construct(string  $strategyName, int $code = 0, \Throwable $previous)
    {
        $message = sprintf(
            'No Strategy with name "%s" found',
            $strategyName
        );
        parent::__construct($message, $code, $previous);
    }
}
