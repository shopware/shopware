<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\ApiCaller;
use Shopware\Core\Framework\Api\Acl\IdentityType;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;

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

        static::assertSame(IdentityType::App, $subscriber->type);
        static::assertSame($manifest, $subscriber->manifest);
    }

    #[DataProvider('subscribersWithoutAManifest')]
    public function testASubscriberWithoutAManifestCarriesItsType(Subscriber $subscriber, ?IdentityType $expectedType): void
    {
        static::assertSame($expectedType, $subscriber->type);
        static::assertNull($subscriber->manifest);
    }

    /**
     * @return iterable<string, array{Subscriber, IdentityType|null}>
     */
    public static function subscribersWithoutAManifest(): iterable
    {
        $user = ApiCaller::of(new AdminApiSource('user-id'));
        static::assertNotNull($user);
        $integration = ApiCaller::of(new AdminApiSource(null, 'integration-id'));
        static::assertNotNull($integration);

        yield 'a user through the API' => [Subscriber::api($user), IdentityType::User];
        yield 'an integration through the API' => [Subscriber::api($integration), IdentityType::Integration];
        yield 'no subscriber at all' => [Subscriber::none(), null];
    }
}
