<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Storefront;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\Storefront;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Storefront::class)]
class StorefrontTest extends TestCase
{
    public function testFromXml(): void
    {
        $storefront = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml')->getStorefront();

        static::assertNotNull($storefront);
        static::assertSame(100, $storefront->getTemplateLoadPriority());

        $seoUrls = $storefront->getSeoUrls();
        static::assertCount(2, $seoUrls);
        static::assertSame('imprint', $seoUrls[0]->getName());
        static::assertSame('blog-detail', $seoUrls[1]->getName());
    }

    public function testFromXmlWithoutSeoUrls(): void
    {
        $storefront = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test-manifest-withoutShippingMethods.xml')->getStorefront();

        static::assertNotNull($storefront);
        static::assertSame(100, $storefront->getTemplateLoadPriority());
        static::assertSame([], $storefront->getSeoUrls());
    }
}
