<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Breadcrumb\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;

/**
 * @internal
 */
#[CoversClass(Breadcrumb::class)]
class BreadcrumbTest extends TestCase
{
    public function testBreadcrumbConstructorSetsPropertiesCorrectly(): void
    {
        $translated = ['description' => 'Home page'];
        $seoUrls = [['seoPath' => 'home-seo']];
        $breadcrumb = new Breadcrumb('Home', 'category-id', 'page', $translated, 'home', $seoUrls);

        static::assertSame('Home', $breadcrumb->name);
        static::assertSame('category-id', $breadcrumb->categoryId);
        static::assertSame('page', $breadcrumb->type);
        static::assertSame('home', $breadcrumb->path);
        static::assertSame($translated, $breadcrumb->translated);
        static::assertSame([['seoPath' => 'home-seo']], $breadcrumb->seoUrls);
    }

    public function testBreadcrumbConstructorSetsDefaultValues(): void
    {
        $breadcrumb = new Breadcrumb('Home');

        static::assertSame('Home', $breadcrumb->name);
        static::assertSame('', $breadcrumb->path);
        static::assertSame([], $breadcrumb->seoUrls);
    }

    public function testGetApiAliasReturnsCorrectAlias(): void
    {
        $breadcrumb = new Breadcrumb('Home');

        static::assertSame('breadcrumb', $breadcrumb->getApiAlias());
    }
}
