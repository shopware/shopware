<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Throwable;

class ImpossibleWriteOrderException extends ShopwareHttpException
{
    protected $code = 'IMPOSSIBLE-WRITE-ORDER';

    public function __construct(array $remaining, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Can not resolve write order for provided data. Remaining write order classes: %s ', implode(',', $remaining));

        parent::__construct($message, $code, $previous);
    }
}
