<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Subscriber;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Shopware\Core\System\SalesChannel\Exception\DefaultSalesChannelTypeCannotBeDeleted;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelTypeValidator implements EventSubscriberInterface
{
    private const PROTECTED_SALES_CHANNEL_TYPE_IDS = [
        Defaults::SALES_CHANNEL_TYPE_API,
        Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
        Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON,
        Defaults::SALES_CHANNEL_TYPE_AGENTIC_AI,
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preWriteValidateEvent',
        ];
    }

    public function preWriteValidateEvent(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if (!$command instanceof DeleteCommand || $command->getEntityName() !== SalesChannelTypeDefinition::ENTITY_NAME) {
                continue;
            }

            $id = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);

            if (\in_array($id, self::PROTECTED_SALES_CHANNEL_TYPE_IDS, true)) {
                $event->getExceptions()->add(new DefaultSalesChannelTypeCannotBeDeleted($id));
            }
        }
    }
}
