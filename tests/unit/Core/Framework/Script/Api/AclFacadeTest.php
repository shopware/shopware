<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Script\Api\AclFacade;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AclFacade::class)]
class AclFacadeTest extends TestCase
{
    public function testCan(): void
    {
        $source = new AdminApiSource(null, Uuid::randomHex());
        $source->setIsAdmin(false);
        $source->setPermissions(['product:read']);

        $context = Context::createCLIContext($source);

        $facade = new AclFacade($context);

        static::assertTrue($facade->can('product:read'));
        static::assertFalse($facade->can('order:read'));
    }
}
