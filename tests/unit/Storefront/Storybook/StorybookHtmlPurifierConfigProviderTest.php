<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Storybook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Storybook\StorybookHtmlPurifierConfigProvider;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorybookHtmlPurifierConfigProvider::class)]
class StorybookHtmlPurifierConfigProviderTest extends TestCase
{
    public function testKeepsFormControlsAndResolvesRelativeUrlsAgainstTheShopOrigin(): void
    {
        $provider = new StorybookHtmlPurifierConfigProvider('https://shop.example');
        $purifier = new \HTMLPurifier($provider->getConfig());

        $result = $purifier->purify('<input type="text" value="example"><a href="/product">Product</a>');

        static::assertStringContainsString('<input type="text" value="example"', $result);
        static::assertStringContainsString('href="https://shop.example/product"', $result);
    }
}
