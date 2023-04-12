<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Logging\Event;

use Monolog\Level;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\LogAware;

/**
 * @internal
 */
class LogAwareTestFlowEvent extends TestFlowBusinessEvent implements LogAware, FlowEventAware
{
    final public const EVENT_NAME = 'test.flow_event.log_aware';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getLogData(): array
    {
        return ['awesomekey' => 'awesomevalue'];
    }

    /**
     * @deprecated tag:v6.6.0 - reason:return-type-change - Return type will change to @see \Monolog\Level
     */
    public function getLogLevel(): int
    {
        return Level::Emergency->value;
    }
}
