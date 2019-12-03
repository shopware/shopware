<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

use Monolog\Logger;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\BusinessEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LoggingService implements EventSubscriberInterface
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $subscribedEvents;

    /**
     * @var string
     */
    protected $environment;

    public function __construct(
        string $kernelEnv,
        Logger $logger
    ) {
        $this->logger = $logger;
        $this->environment = $kernelEnv;
    }

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

    public static function getSubscribedEvents(): array
    {
        return [BusinessEvents::GLOBAL_EVENT => 'logBusinessEvent'];
    }
}
