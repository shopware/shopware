<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class AppNotFoundException extends \Exception
{
    public function __construct(string $appId)
    {
        parent::__construct(sprintf('App for ID: "%s" could not be found.', $appId));
    }
}
