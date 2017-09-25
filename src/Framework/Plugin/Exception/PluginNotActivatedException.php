<?php

namespace Shopware\Framework\Plugin\Exception;

use Throwable;

class PluginNotActivatedException extends \Exception
{
    public function __construct(string $pluginName, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Plugin "%s" is not activated.', $pluginName);

        parent::__construct($message, $code, $previous);
    }

}