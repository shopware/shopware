<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Logging;

use Doctrine\DBAL\Connection;
use Monolog\Logger;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Event\BusinessEvent;
use Shopware\Core\Framework\Event\BusinessEvents;
use Shopware\Core\Framework\Logging\Filter\LogFilterRegistry;
use Shopware\Core\Framework\Logging\Monolog\DALHandler;
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

    /**
     * @var EntityRepositoryInterface
     */
    protected $logEntryRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LogFilterRegistry
     */
    private $logFilterRegistry;

    public function __construct(string $kernelEnv,
                                EntityRepositoryInterface $logEntryRepository,
                                Connection $connection,
                                LogFilterRegistry $filterRegistry)
    {
        $this->logger = new Logger('shopware');
//        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/sw_log.log', Logger::API));
//        $this->logger->pushHandler(new BrowserConsoleHandler(Logger::API));
        $this->logger->pushHandler(new DALHandler($logEntryRepository, $connection));
        $this->environment = $kernelEnv;
        $this->logEntryRepository = $logEntryRepository;
        $this->logFilterRegistry = $filterRegistry;
    }

    public function logBusinessEvent(BusinessEvent $event): void
    {
        $additionalData = [];

        $filter = $this->logFilterRegistry->getFilter($event->getEvent()->getName());
        if ($filter) {
            $additionalData = $filter->filterEventData($event->getEvent());
        }

        $this->logger->addDebug($event->getEvent()->getName(),
            [
                'source' => 'core',
                'environment' => $this->environment,
                'additionalData' => $additionalData,
            ]
        );
    }

    public static function getSubscribedEvents(): array
    {
        // todo: crank up priority ?
        return [BusinessEvents::GLOBAL_EVENT => ['logBusinessEvent']];
    }
}
