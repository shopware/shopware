<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventAction;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - reason:remove-subscriber - Will be removed in v6.5.0.
 */
class EventActionSubscriber implements EventSubscriberInterface
{
    private Connection $connection;

    /**
     * @internal
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
    {
        return [
            MailTemplateEvents::MAIL_TEMPLATE_DELETED_EVENT => 'deleted',
        ];
    }

    public function deleted(EntityDeletedEvent $event): void
    {
        if (Feature::isActive('FEATURE_NEXT_17858')) {
            return;
        }

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
