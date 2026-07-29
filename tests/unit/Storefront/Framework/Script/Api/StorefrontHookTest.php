<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Shopware\Storefront\Framework\Script\Api\StorefrontHook;
use Shopware\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacadeHookFactory;

/**
 * @internal
 */
#[CoversClass(StorefrontHook::class)]
class StorefrontHookTest extends TestCase
{
    #[TestDox('Uses the Storefront response factory (with render support), not the core one')]
    public function testGetServiceIdsUsesStorefrontResponseFactory(): void
    {
        $serviceIds = StorefrontHook::getServiceIds();

        static::assertContains(StorefrontScriptResponseFactoryFacadeHookFactory::class, $serviceIds);
        static::assertNotContains(ScriptResponseFactoryFacadeHookFactory::class, $serviceIds);
    }
}
