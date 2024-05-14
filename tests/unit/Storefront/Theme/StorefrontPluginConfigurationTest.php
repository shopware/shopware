<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

/**
 * @internal
 */
#[CoversClass(StorefrontPluginConfiguration::class)]
class StorefrontPluginConfigurationTest extends TestCase
{
    public function testAdditionalBundlesIsFalse(): void
    {
        $config = new StorefrontPluginConfiguration('name');

        static::assertFalse($config->hasAdditionalBundles());
    }

    public function testNameIsSet(): void
    {
        $config = new StorefrontPluginConfiguration('name');

        static::assertEquals('name', $config->getTechnicalName());
    }
}
