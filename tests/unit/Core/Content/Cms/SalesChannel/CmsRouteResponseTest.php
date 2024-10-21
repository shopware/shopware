<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\CmsRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CmsRouteResponse::class)]
class CmsRouteResponseTest extends TestCase
{
    public function testGetCmsPage(): void
    {
        $expected = new CmsPageEntity();
        $response = new CmsRouteResponse($expected);

        $actual = $response->getCmsPage();
        static::assertSame($expected, $actual);
    }
}
