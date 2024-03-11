<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\StorefrontPluginConfiguration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * @internal
 */
#[CoversClass(StorefrontPluginConfiguration::class)]
class StorefrontPluginConfigurationTest extends TestCase
{
    public function testAssetName(): void
    {
        $config = new StorefrontPluginConfiguration('SwagPayPal');
        static::assertEquals('swag-pay-pal', $config->getAssetName());
    }
}
