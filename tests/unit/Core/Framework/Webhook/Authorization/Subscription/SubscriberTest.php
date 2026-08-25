<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Subscriber::class)]
class SubscriberTest extends TestCase
{
    public function testAnAppSubscriberCarriesItsManifest(): void
    {
        $manifest = static::createStub(Manifest::class);

        $subscriber = Subscriber::app($manifest);

        static::assertSame(SubscriberType::App, $subscriber->type);
        static::assertSame($manifest, $subscriber->manifest);
    }

    #[DataProvider('subscribersWithoutAManifest')]
    public function testASubscriberWithoutAManifestCarriesItsType(Subscriber $subscriber, SubscriberType $expectedType): void
    {
        static::assertSame($expectedType, $subscriber->type);
        static::assertNull($subscriber->manifest);
    }

    /**
     * @return iterable<string, array{Subscriber, SubscriberType}>
     */
    public static function subscribersWithoutAManifest(): iterable
    {
        yield 'an admin user' => [Subscriber::admin(), SubscriberType::Admin];
        yield 'a non-admin user' => [Subscriber::user(), SubscriberType::User];
        yield 'an integration' => [Subscriber::integration(), SubscriberType::Integration];
        yield 'an app integration' => [Subscriber::appIntegration(), SubscriberType::AppIntegration];
        yield 'no subscriber at all' => [Subscriber::none(), SubscriberType::None];
    }

    public function testAnAdminSourceIsAnAdmin(): void
    {
        $source = new AdminApiSource(Uuid::randomHex());
        $source->setIsAdmin(true);

        static::assertEquals(Subscriber::admin(), Subscriber::fromSource($source));
    }

    public function testAnIntegrationSourceIsAnIntegration(): void
    {
        static::assertEquals(Subscriber::integration(), Subscriber::fromSource(new AdminApiSource(null, Uuid::randomHex())));
    }

    public function testAUserSourceIsAUser(): void
    {
        static::assertEquals(Subscriber::user(), Subscriber::fromSource(new AdminApiSource(Uuid::randomHex())));
    }
}
