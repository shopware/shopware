<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Subscriber;

use Doctrine\DBAL\Connection;
use Monolog\Level;
use Shopware\Core\Content\Mail\Service\MailLogData;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\FailedMessageEvent;

/**
 * @internal
 */
#[Package('after-sales')]
class FailedMessageSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FailedMessageEvent::class => 'logEvent',
        ];
    }

    public function logEvent(FailedMessageEvent $event): void
    {
        $sensitive = MailLogData::isSensitiveMessage($event->getMessage());

        try {
            $entry = [
                'id' => Uuid::randomBytes(),
                'message' => 'mail.message.failed',
                'level' => Level::Error->value,
                'channel' => 'mail',
                'context' => json_encode($sensitive ? MailLogData::create(
                    MailLogData::fromMessage($event->getMessage()),
                    $event->getError(),
                ) : [
                    'error' => $event->getError()->getMessage(),
                    'rawMessage' => $event->getMessage()->toString(),
                ], \JSON_THROW_ON_ERROR),
                'extra' => $sensitive ? null : json_encode([
                    'exception' => $event->getError()->__toString(),
                    'trace' => $event->getError()->getTraceAsString(),
                ], \JSON_THROW_ON_ERROR),
                'updated_at' => null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ];

            $this->connection->insert('log_entry', $entry);
        } catch (\Throwable) {
            $entry['context'] = json_encode([]);
            $entry['extra'] = json_encode([]);

            $this->connection->insert('log_entry', $entry);
        }
    }
}
