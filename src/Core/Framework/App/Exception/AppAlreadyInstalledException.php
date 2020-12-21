<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

class AppAlreadyInstalledException extends \Exception
{
    public function __construct(string $appName)
    {
        parent::__construct(sprintf('App with name "%s" is already installed.', $appName));
    }
}
