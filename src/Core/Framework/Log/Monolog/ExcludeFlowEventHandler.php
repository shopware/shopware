<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log\Monolog;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ExcludeFlowEventHandler extends AbstractHandler
{
    /**
     * @internal
     *
     * @param array<int, string> $excludeEvents
     */
    public function __construct(
        private readonly HandlerInterface $handler,
        private readonly array $excludeEvents = []
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(LogRecord $record): bool
    {
        if ($record->channel !== 'business_events') {
            return $this->handler->handle($record);
        }

        // exclude if the flow event is in excluded list
        if (\in_array($record->message, $this->excludeEvents, true)) {
            return true;
        }

        // exclude if the mail event's origin event is in exclude list
        $eventName = $record->context['additionalData']['eventName'] ?? null;

        if ($eventName && \in_array($eventName, $this->excludeEvents, true)) {
            return true;
        }

        return $this->handler->handle($record);
    }
}
