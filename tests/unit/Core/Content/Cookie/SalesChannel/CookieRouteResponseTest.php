<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRouteResponse;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CookieRouteResponse::class)]
class CookieRouteResponseTest extends TestCase
{
    public function testExposesTheWrappedGroupsHashAndLanguage(): void
    {
        $groups = new CookieGroupCollection();

        $response = new CookieRouteResponse($groups, 'hash-value', 'language-id');

        static::assertSame($groups, $response->getCookieGroups());
        static::assertSame('hash-value', $response->getHash());
        static::assertSame('language-id', $response->getLanguageId());
        static::assertSame('cookie_groups_hash', $response->getObject()->getApiAlias());
    }

    public function testLanguageIdDefaultsToAnEmptyString(): void
    {
        static::assertSame('', (new CookieRouteResponse(new CookieGroupCollection(), 'hash-value'))->getLanguageId());
    }
}
