<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Controller\StorybookController;
use Shopware\Storefront\Framework\Routing\StorybookRouteScopeAllowList;

/**
 * @internal
 */
#[CoversClass(StorybookRouteScopeAllowList::class)]
class StorybookRouteScopeAllowListTest extends TestCase
{
    private StorybookRouteScopeAllowList $allowList;

    protected function setUp(): void
    {
        $this->allowList = new StorybookRouteScopeAllowList();
    }

    public function testAppliesToStorybookController(): void
    {
        static::assertTrue($this->allowList->applies(StorybookController::class));
    }

    public function testDoesNotApplyToOtherControllers(): void
    {
        static::assertFalse($this->allowList->applies(\stdClass::class));
    }
}
