<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.9.0 reason:remove-subscriber - Removed together with the legacy `document_type_id` foreign key when v1 is removed.
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\DocumentV2\Subscriber\DocumentTypeNameSyncSubscriberTest
 */
#[Package('after-sales')]
class DocumentTypeNameSyncSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'writeTypeName',
        ];
    }

    public function writeTypeName(EntityWriteEvent $event): void
    {
        $commands = [
            ...$event->getCommandsForEntity(DocumentDefinition::ENTITY_NAME),
            ...$event->getCommandsForEntity(DocumentBaseConfigDefinition::ENTITY_NAME),
            ...$event->getCommandsForEntity(DocumentBaseConfigSalesChannelDefinition::ENTITY_NAME),
        ];

        foreach ($commands as $command) {
            if ($command instanceof DeleteCommand) {
                continue;
            }

            $this->syncTypeName($command);
        }
    }

    private function syncTypeName(WriteCommand $command): void
    {
        if (!$command->hasField('document_type_id') || $command->hasField('type_name')) {
            return;
        }

        $documentTypeId = $command->getPayload()['document_type_id'] ?? null;

        if ($documentTypeId === null) {
            $command->addPayload('type_name', null);

            return;
        }

        $technicalName = $this->connection->fetchOne(
            'SELECT `technical_name` FROM `document_type` WHERE `id` = :id LIMIT 1',
            ['id' => $documentTypeId],
        );

        $command->addPayload('type_name', \is_string($technicalName) ? $technicalName : null);
    }
}
