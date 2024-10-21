<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Breadcrumb\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;

/**
 * @internal
 */
#[CoversClass(BreadcrumbCollection::class)]
class BreadcrumbCollectionTest extends TestCase
{
    public function testGetBreadcrumbReturnsCorrectArray(): void
    {
        $breadcrumb0 = new Breadcrumb('Home', '/');
        $breadcrumb1 = new Breadcrumb('Products', '/products');
        $breadcrumb2 = new Breadcrumb('Home', '/products/electronics');

        $breadcrumbsCollection = [
            0 => $breadcrumb0,
            1 => $breadcrumb1,
            2 => $breadcrumb2,
        ];

        $breadcrumbCollection = new BreadcrumbCollection($breadcrumbsCollection);

        $result = $breadcrumbCollection->getBreadcrumb(0);

        static::assertSame($breadcrumb0, $result);
    }

    public function testGetBreadcrumbThrowsExceptionForInvalidIndex(): void
    {
        $breadcrumb0 = new Breadcrumb('Home', '/');
        $breadcrumbsCollection = [
            0 => $breadcrumb0,
        ];

        $breadcrumbCollection = new BreadcrumbCollection($breadcrumbsCollection);

        static::assertNull($breadcrumbCollection->getBreadcrumb(1));
    }

    public function testGetBreadcrumbsReturnsAllBreadcrumbs(): void
    {
        $breadcrumb0 = new Breadcrumb('Home', 'home');
        $breadcrumb1 = new Breadcrumb('Category', 'category');

        $breadcrumbsCollection = [
            0 => $breadcrumb0,
            1 => $breadcrumb1,
        ];

        $breadcrumbCollection = new BreadcrumbCollection($breadcrumbsCollection);

        $result = $breadcrumbCollection->getBreadcrumbs();

        static::assertSame($breadcrumbsCollection, $result);
    }

    public function testGetApiAliasReturnsCorrectAlias(): void
    {
        $breadcrumbCollection = new BreadcrumbCollection([]);

        $result = $breadcrumbCollection->getApiAlias();

        static::assertSame('breadcrumb_collection', $result);
    }
}
