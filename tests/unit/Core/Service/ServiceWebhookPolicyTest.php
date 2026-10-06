<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\Service\Event\CommercialLicenseProvidedEvent;
use Shopware\Core\Service\ServiceWebhookPolicy;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceWebhookPolicy::class)]
class ServiceWebhookPolicyTest extends TestCase
{
    public function testItCoversTheLicenseEvent(): void
    {
        static::assertTrue($this->createPolicy()->handles(CommercialLicenseProvidedEvent::NAME));
        static::assertFalse($this->createPolicy()->handles('checkout.customer.login'));
    }

    public function testASelfManagedAppMayReceiveIt(): void
    {
        static::assertTrue($this->createPolicy(appIsSelfManaged: true)->permitsDelivery(static::createStub(Hookable::class), $this->createWebhook()));
    }

    public function testAnAppThatIsNotSelfManagedMayNotReceiveIt(): void
    {
        static::assertFalse($this->createPolicy(appIsSelfManaged: false)->permitsDelivery(static::createStub(Hookable::class), $this->createWebhook()));
    }

    public function testAnAppThatIsNotActiveMayNotReceiveIt(): void
    {
        $policy = $this->createPolicy(appIsSelfManaged: true);

        static::assertFalse($policy->permitsDelivery(static::createStub(Hookable::class), $this->createWebhook(appName: 'OtherApp')));
    }

    public function testAWebhookWithoutAnAppMayNotReceiveIt(): void
    {
        $policy = $this->createPolicy(appIsSelfManaged: true);

        static::assertFalse($policy->permitsDelivery(static::createStub(Hookable::class), $this->createWebhook(appName: null)));
    }

    public function testASelfManagedManifestMaySubscribe(): void
    {
        static::assertTrue($this->createPolicy()->permitsSubscription(CommercialLicenseProvidedEvent::NAME, Subscriber::app($this->createManifest(selfManaged: true))));
    }

    public function testAManifestThatIsNotSelfManagedMayNotSubscribe(): void
    {
        static::assertFalse($this->createPolicy()->permitsSubscription(CommercialLicenseProvidedEvent::NAME, Subscriber::app($this->createManifest(selfManaged: false))));
    }

    public function testASubscriberWithoutAManifestMayNotSubscribe(): void
    {
        static::assertFalse($this->createPolicy()->permitsSubscription(CommercialLicenseProvidedEvent::NAME, Subscriber::user()));
    }

    private function createPolicy(bool $appIsSelfManaged = false): ServiceWebhookPolicy
    {
        $activeAppsLoader = static::createStub(ActiveAppsLoader::class);
        $activeAppsLoader->method('getActiveApps')->willReturn([[
            'name' => 'SwagApp',
            'path' => 'custom/apps/SwagApp',
            'author' => null,
            'selfManaged' => $appIsSelfManaged,
        ]]);

        return new ServiceWebhookPolicy($activeAppsLoader);
    }

    private function createWebhook(?string $appName = 'SwagApp'): Webhook
    {
        return new Webhook(
            id: 'webhook-id',
            webhookName: 'hook',
            eventName: CommercialLicenseProvidedEvent::NAME,
            url: 'https://example.com',
            onlyLiveVersion: false,
            appId: $appName === null ? null : 'app-id',
            appName: $appName,
            appSourceType: null,
            appActive: true,
            appVersion: null,
            appSecret: null,
            appAclRoleId: null,
        );
    }

    private function createManifest(bool $selfManaged): Manifest
    {
        $metadata = static::createStub(Metadata::class);
        $metadata->method('isSelfManaged')->willReturn($selfManaged);

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getMetadata')->willReturn($metadata);

        return $manifest;
    }
}
