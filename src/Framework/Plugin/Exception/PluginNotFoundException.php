<?php declare(strict_types=1);

namespace Shopware\Framework\Plugin\Exception;

use Throwable;

class PluginNotFoundException extends \Exception
{
    public function __construct(string $pluginName, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Plugin by name "%s" not found.', $pluginName);

        parent::__construct($message, $code, $previous);
    }
}
