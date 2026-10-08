<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - reason:remove-subscriber - The price basis is part of every create payload from then on, the definition defaults it to `gross`.
 */
#[Package('discovery')]
final class CustomerGroupPriceBasisSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'derivePriceBasis',
        ];
    }

    public function derivePriceBasis(EntityWriteEvent $event): void
    {
        foreach ($event->getCommandsForEntity(CustomerGroupDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand) {
                continue;
            }

            $payload = $command->getPayload();
            if (($payload['price_basis'] ?? null) !== null) {
                continue;
            }

            $command->addPayload(
                'price_basis',
                (bool) ($payload['display_gross'] ?? true) ? CustomerGroupEntity::PRICE_BASIS_GROSS : CustomerGroupEntity::PRICE_BASIS_NET
            );
        }
    }
}
