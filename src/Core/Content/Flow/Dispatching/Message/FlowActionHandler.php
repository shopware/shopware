<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Message;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\TransactionalAction;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler(handles: FlowActionMessage::class)]
#[Package('after-sales')]
final readonly class FlowActionHandler
{
    /**
     * @var array<string, FlowAction>
     */
    private readonly array $actions;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        $actions
    ) {
        $this->actions = $actions instanceof \Traversable ? iterator_to_array($actions) : $actions;
    }

    public function __invoke(FlowActionMessage $message): void
    {
        $action = $this->actions[$message->actionName] ?? null;

        if (!$action instanceof FlowAction) {
            return;
        }

        $event = $message->event;

        if (!$action instanceof TransactionalAction) {
            $action->handleFlow($event);

            return;
        }

        $this->connection->beginTransaction();

        try {
            $action->handleFlow($event);
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw FlowException::transactionFailed($e);
        }

        try {
            $this->connection->commit();
        } catch (DBALException $e) {
            $this->connection->rollBack();

            throw FlowException::transactionFailed($e);
        }
    }
}
