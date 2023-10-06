<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Monolog\Level;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @deprecated tag:v6.6.0 - reason:class-hierarchy-change - extends of FlowEventAware will be removed, implement the interface inside your event
 */
#[Package('core')]
interface LogAware extends FlowEventAware
{
    /**
     * @return array<string, mixed>
     */
    public function getLogData(): array;

    /**
     * @deprecated tag:v6.6.0 - reason:return-type-change - Return type will change to @see \Monolog\Level
     *
     * @return value-of<Level::VALUES>
     */
    public function getLogLevel(): int;
}
