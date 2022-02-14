<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal not intended for decoration or replacement
 */
class FlowLoader extends AbstractFlowLoader
{
    private Connection $connection;

    private LoggerInterface $logger;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getDecorated(): AbstractFlowLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(): array
    {
        $flows = $this->connection->fetchAllAssociative(
            'SELECT `event_name`, LOWER(HEX(`id`)) as `id`, `name`, `payload` FROM `flow`
                WHERE `active` = 1 AND `invalid` = 0 AND `payload` IS NOT NULL
                ORDER BY `priority` DESC',
        );

        if (empty($flows)) {
            return [];
        }

        foreach ($flows as $key => $flow) {
            try {
                $payload = unserialize($flow['payload']);
            } catch (\Throwable $e) {
                $this->logger->error(
                    "Flow payload is invalid:\n"
                    . 'Flow name: ' . $flow['name'] . "\n"
                    . 'Flow id: ' . $flow['id'] . "\n"
                    . $e->getMessage() . "\n"
                    . 'Error Code: ' . $e->getCode() . "\n"
                );

                continue;
            }

            $flows[$key]['payload'] = $payload;
        }

        $this->loadAppActionListener();

        return FetchModeHelper::group($flows);
    }

    private function loadAppActionListener(): void
    {
        $appFlowActions = $this->connection->fetchFirstColumn(
            'SELECT `app_flow_action`.`name`
            FROM `app_flow_action`
            LEFT JOIN `app` ON `app_flow_action`.`app_id` = `app`.`id`
            WHERE `app`.`active` = 1',
        );

        foreach ($appFlowActions as $action) {
            $this->eventDispatcher->addListener($action, function (FlowEvent $event) use ($action): void {
                $eventName = AppFlowActionEvent::PREFIX . $action;

                $this->eventDispatcher->dispatch(new AppFlowActionEvent($event), $eventName);
            });
        }
    }
}
