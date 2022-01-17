<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Monolog\Logger;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\BusinessEvents;
use Shopware\Core\Framework\Event\FlowLogEvent;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LoggingService implements EventSubscriberInterface
{
    protected Logger $logger;

    protected array $subscribedEvents;

    protected string $environment;

    public function __construct(
        string $kernelEnv,
        Logger $logger
    ) {
        $this->logger = $logger;
        $this->environment = $kernelEnv;
    }

    /**
     * @deprecated tag:v6.5.0 - Function is deprecated.
     */
    public function logBusinessEvent(BusinessEvent $event): void
    {
        $innerEvent = $event->getEvent();

        $additionalData = [];
        $logLevel = Logger::DEBUG;

        if ($innerEvent instanceof LogAwareBusinessEventInterface) {
            $logLevel = $innerEvent->getLogLevel();
            $additionalData = $innerEvent->getLogData();
        }

        $this->logger->addRecord(
            $logLevel,
            $innerEvent->getName(),
            [
                'source' => 'core',
                'environment' => $this->environment,
                'additionalData' => $additionalData,
            ]
        );
    }

    public function logFlowEvent(FlowLogEvent $event): void
    {
        $innerEvent = $event->getEvent();

        $additionalData = [];
        $logLevel = Logger::DEBUG;

        if ($innerEvent instanceof LogAware) {
            $logLevel = $innerEvent->getLogLevel();
            $additionalData = $innerEvent->getLogData();
        }

        $this->logger->addRecord(
            $logLevel,
            $innerEvent->getName(),
            [
                'source' => 'core',
                'environment' => $this->environment,
                'additionalData' => $additionalData,
            ]
        );
    }

    public static function getSubscribedEvents(): array
    {
        if (Feature::isActive('v6.5.0.0')) {
            return [FlowLogEvent::NAME => 'logFlowEvent'];
        }

        return [BusinessEvents::GLOBAL_EVENT => 'logBusinessEvent'];
    }
}
