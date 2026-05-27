<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Service\Event\CommercialLicenseProvidedEvent;

/**
 * @internal
 */
#[CoversClass(CommercialLicenseProvidedEvent::class)]
#[Package('framework')]
class CommercialLicenseProvidedEventTest extends TestCase
{
    public function testPayload(): void
    {
        $event = CommercialLicenseProvidedEvent::forAll('license-key', 'license-host');

        static::assertSame(CommercialLicenseProvidedEvent::NAME, $event->getName());
        static::assertSame([
            'licenseKey' => 'license-key',
            'licenseHost' => 'license-host',
        ], $event->getWebhookPayload());
    }

    public function testEventCanBeProvidedToAllServices(): void
    {
        $event = CommercialLicenseProvidedEvent::forAll('license-key', 'license-host');

        static::assertTrue($event->isAllowed('service-app-a', new AclPrivilegeCollection([])));
        static::assertTrue($event->isAllowed('service-app-b', new AclPrivilegeCollection([])));
    }

    public function testEventCanBeProvidedToOneService(): void
    {
        $event = CommercialLicenseProvidedEvent::forService('service-app-a', 'license-key', 'license-host');

        static::assertTrue($event->isAllowed('service-app-a', new AclPrivilegeCollection([])));
        static::assertFalse($event->isAllowed('service-app-b', new AclPrivilegeCollection([])));
    }
}
