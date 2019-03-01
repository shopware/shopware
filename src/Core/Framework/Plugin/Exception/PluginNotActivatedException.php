<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class PluginNotActivatedException extends ShopwareHttpException
{
    protected $code = 'PLUGIN-NOT-ACTIVATED';

    public function __construct(string $pluginName, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Plugin "%s" is not activated at all', $pluginName);

        parent::__construct($message, $code, $previous);
    }
}
