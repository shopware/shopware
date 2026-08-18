<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Cookie\Hook;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class CookieGroupCollectHookTest extends TestCase
{
    use AppSystemTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testAppScriptCanManipulateCookieGroups(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/cookieScriptApp');

        $cookieGroups = static::getContainer()->get(CookieProvider::class)
            ->getCookieGroups(new Request(), Generator::generateSalesChannelContext());

        static::assertNull($cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL));
        static::assertNotNull($cookieGroups->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED));

        $appGroup = $cookieGroups->get('swag.cookies.group');
        static::assertNotNull($appGroup);

        $entries = $appGroup->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);
        static::assertNotNull($entries->get('swag-app-keep'));
        static::assertNull($entries->get('swag-app-conditional'));
    }
}
