<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Logging\Event;

use Monolog\Logger;
use Shopware\Core\Framework\Log\LogAwareBusinessEventInterface;
use Shopware\Core\Framework\Test\Event\TestBusinessEvent;

class LogAwareTestBusinessEvent extends TestBusinessEvent implements LogAwareBusinessEventInterface
{
    public const EVENT_NAME = 'test.business_event.log_aware';

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
