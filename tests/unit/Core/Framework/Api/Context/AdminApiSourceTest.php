<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Log\Package;

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
}
