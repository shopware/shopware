<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Throwable;

class PluginNotInstalledException extends \Exception
{
    public function __construct(string $pluginName, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Plugin "%s" is not installed.', $pluginName);

        parent::__construct($message, $code, $previous);
    }
}
