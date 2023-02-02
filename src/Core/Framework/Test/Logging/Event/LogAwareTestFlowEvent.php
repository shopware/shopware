<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Logging\Event;

use Monolog\Logger;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Shopware\Core\Framework\Log\LogAware;

/**
 * @internal
 */
class LogAwareTestFlowEvent extends TestFlowBusinessEvent implements LogAware
{
    public const EVENT_NAME = 'test.flow_event.log_aware';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getLogData(): array
    {
        return ['awesomekey' => 'awesomevalue'];
    }

    public function getLogLevel(): int
    {
        return Logger::EMERGENCY;
    }
}
