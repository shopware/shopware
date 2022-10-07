<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Subscriber;

use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCodeValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Country\CountryEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class CountryEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CountryEvents::COUNTRY_LOADED_EVENT => 'setDefault',
        ];
    }

    public function setDefault(EntityLoadedEvent $event): void
    {
        /** @var CountryEntity $entity */
        foreach ($event->getEntities() as $entity) {
            $this->setCountryHandlerRuntimeFields($entity);
        }
    }

    private function setCountryHandlerRuntimeFields(CountryEntity $countryEntity): void
    {
        $patterns = CustomerZipCodeValidator::PATTERNS;

        $defaultPattern = \array_key_exists((string) $countryEntity->getIso(), $patterns) ? $patterns[$countryEntity->getIso()] : '';

        $countryEntity->setDefaultPostalCodePattern($defaultPattern);
    }
}
