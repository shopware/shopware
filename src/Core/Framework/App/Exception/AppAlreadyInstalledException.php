<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class AppAlreadyInstalledException extends \Exception
{
    public function __construct(string $appName)
    {
        parent::__construct(sprintf('App with name "%s" is already installed.', $appName));
    }
}
