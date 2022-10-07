<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Country\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Country\CountryEvents;
use Shopware\Core\System\Country\Subscriber\CountryEventSubscriber;

/**
 * @internal
 * @covers \Shopware\Core\System\Country\Subscriber\CountryEventSubscriber
 */
class CountryEventSubscriberTest extends TestCase
{
    public function testHasEvents(): void
    {
        $expected = [CountryEvents::COUNTRY_LOADED_EVENT => 'setDefault'];

        static::assertSame($expected, CountryEventSubscriber::getSubscribedEvents());
    }

    public function testEntityLoadedEvent(): void
    {
        $entity = new CountryEntity();
        $entity->setId(Uuid::randomHex());
        $entity->setIso('DE');
        $entity->setDefaultPostalCodePattern(null);

        $subscriber = new CountryEventSubscriber();
        $event = new EntityLoadedEvent(new CountryDefinition(), [$entity], Context::createDefaultContext());

        static::assertNull($entity->getDefaultPostalCodePattern());

        $subscriber->setDefault($event);

        static::assertNotNull($entity->getDefaultPostalCodePattern());
        static::assertSame('\d{5}', $entity->getDefaultPostalCodePattern());
    }
}
