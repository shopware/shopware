<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

class NoAppUrlChangeDetectedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('No APP_URL change was detected, cannot run AppUrlChange strategies.');
    }
}
