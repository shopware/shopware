<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Throwable;

class PluginToShopwareCompatibilityException extends ShopwareHttpException
{
    protected $code = 'PLUGIN-NOT-COMPATIBLE-WITH-SHOPWARE-VERSION';

    public function __construct(string $message, int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
