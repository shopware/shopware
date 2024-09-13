<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Page\LandingPage\LandingPage;
use Shopware\Storefront\Page\LandingPage\LandingPageLoadedHook;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(LandingPageLoadedHook::class)]
class LandingPageLoadedHookTest extends TestCase
{
    public function testLandingPageLoadedHook(): void
    {
        $page = new LandingPage();
        $context = Generator::createSalesChannelContext();

        $hook = new LandingPageLoadedHook($page, $context);
        static::assertSame('landing-page-loaded', $hook->getName());
        static::assertSame($page, $hook->getPage());
    }
}
