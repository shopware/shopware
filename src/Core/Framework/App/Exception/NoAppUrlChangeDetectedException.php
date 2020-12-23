<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class NoAppUrlChangeDetectedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('No APP_URL change was detected, cannot run AppUrlChange strategies.');
    }
}
