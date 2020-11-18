<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventAction;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventActionSubscriber implements EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function getSubscribedEvents()
    {
        return [
            MailTemplateEvents::MAIL_TEMPLATE_DELETED_EVENT => 'deleted',
        ];
    }

    public function deleted(EntityDeletedEvent $event): void
    {
        $ids = $event->getIds();

        if (empty($ids)) {
            return;
        }

        $this->connection->executeUpdate(
            'DELETE FROM event_action
             WHERE action_name = :action
             AND JSON_UNQUOTE(JSON_EXTRACT(event_action.config, "$.mail_template_id")) IN (:ids)',
            ['action' => 'action.mail.send', 'ids' => $ids],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }
}
