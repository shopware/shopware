<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class CantDeletePluginException extends ShopwareHttpException
{
    protected $code = 'CANT-DELETE-PLUGIN';

    public function __construct(string $reason, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Cant delete plugin. Error: %s', $reason);

        parent::__construct($message, $code, $previous);
    }
}
