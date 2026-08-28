<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Derives the read-only `vatIdCountryId` from the customer's `vatIds` on write, so every write path -
 * Store API, Admin API, Sync API, imports and plugins - stores the same member state instead of only
 * the two store-api routes that used to resolve it themselves.
 *
 * @internal
 */
#[Package('checkout')]
class CustomerVatIdCountrySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly VatIdPatternProvider $vatIdPatternProvider)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'beforeWrite',
        ];
    }

    public function beforeWrite(EntityWriteEvent $event): void
    {
        foreach ($event->getCommandsForEntity(CustomerDefinition::ENTITY_NAME) as $command) {
            if (!$command->hasField('vat_ids')) {
                continue;
            }

            $countryId = $this->vatIdPatternProvider->getCountryIdForVatIds(
                $this->decodeVatIds($command->getPayload()['vat_ids'] ?? null)
            );

            $command->addPayload(
                'vat_id_country_id',
                $countryId === null ? null : Uuid::fromHexToBytes($countryId)
            );
        }
    }

    /**
     * The command payload holds the storage representation the `ListField` serializer produced.
     *
     * @return array<mixed>|null
     */
    private function decodeVatIds(mixed $value): ?array
    {
        if (!\is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return \is_array($decoded) ? $decoded : null;
    }
}
