<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Ownership;

use Shopware\Core\Framework\Api\Acl\ApiCallerType;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Shopware\Core\Framework\Webhook\WebhookException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class RecordOwnerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'recordOwner',
        ];
    }

    public function recordOwner(PreWriteValidationEvent $event): void
    {
        $inserts = array_filter(
            $event->getCommandsForEntity(WebhookDefinition::ENTITY_NAME),
            static fn (WriteCommand $command): bool => $command instanceof InsertCommand
        );

        if ($inserts === []) {
            return;
        }

        $source = $event->getContext()->getSource();
        $ownerType = ApiCallerType::of($source);
        $ownerId = $ownerType?->idOf($source);

        foreach ($inserts as $insert) {
            if ($ownerType !== null && $ownerId !== null) {
                $insert->addPayload(self::ownerColumn($ownerType), Uuid::fromHexToBytes($ownerId));

                continue;
            }

            $payload = $insert->getPayload();
            if (!isset($payload['app_id']) && !isset($payload['owner_user_id']) && !isset($payload['owner_integration_id'])) {
                $event->getExceptions()->add(WebhookException::webhookOwnerMissing());
            }
        }
    }

    private static function ownerColumn(ApiCallerType $type): string
    {
        return match ($type) {
            ApiCallerType::User => 'owner_user_id',
            ApiCallerType::Integration => 'owner_integration_id',
        };
    }
}
