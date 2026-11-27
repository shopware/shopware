<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminApiSource::class)]
class AdminApiSourceTest extends TestCase
{
    public function testPermissions(): void
    {
        $apiSource = new AdminApiSource(null, null);
        $apiSource->setPermissions([
            'product:list',
            'order:delete',
        ]);

        static::assertTrue($apiSource->isAllowed('product:list'));
        static::assertTrue($apiSource->isAllowed('order:delete'));

        static::assertFalse($apiSource->isAllowed('product:delete'));
        static::assertFalse($apiSource->isAllowed('order:list'));
    }

    public function testOnlyAUserWithoutAnIntegrationIsADirectUser(): void
    {
        static::assertTrue((new AdminApiSource('user-id'))->isDirectUser());

        static::assertFalse((new AdminApiSource('user-id', 'integration-id'))->isDirectUser());
        static::assertFalse((new AdminApiSource(null, 'integration-id'))->isDirectUser());
        static::assertFalse((new AdminApiSource(null))->isDirectUser());
        static::assertFalse((new AdminApiSource(''))->isDirectUser());
    }

    public function testOnlyTheDirectUserItselfIsSelf(): void
    {
        static::assertTrue((new AdminApiSource('user-id'))->isSelf('user-id'));

        static::assertFalse((new AdminApiSource('user-id'))->isSelf('other-user-id'));
        static::assertFalse((new AdminApiSource('user-id', 'integration-id'))->isSelf('user-id'));
        static::assertFalse((new AdminApiSource(null))->isSelf('user-id'));
    }

    #[DataProvider('appIdProvider')]
    public function testSurvivesTheMessageQueueSerializer(?string $appId): void
    {
        $source = new AdminApiSource('user-id', 'integration-id');

        if ($appId !== null) {
            $source->setAppId($appId);
        }

        $serializer = Serializer::create();
        $decoded = $serializer->decode($serializer->encode(new Envelope($source)))->getMessage();

        static::assertInstanceOf(AdminApiSource::class, $decoded);
        static::assertSame($appId, $decoded->getAppId());
    }

    public static function appIdProvider(): \Generator
    {
        yield 'a user or integration without an app keeps no app id' => [null];
        yield 'an app integration keeps its app id' => ['app-id'];
    }
}
