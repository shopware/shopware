<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Monolog\Level;
use Shopware\Core\Framework\Event\IsFlowEventAware;

#[IsFlowEventAware]
#[Package('core')]
interface LogAware
{
    /**
     * @return array<string, mixed>
     */
    public function getLogData(): array;

    public function getLogLevel(): Level;
}
