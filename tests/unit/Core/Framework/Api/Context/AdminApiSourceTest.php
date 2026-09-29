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
}
