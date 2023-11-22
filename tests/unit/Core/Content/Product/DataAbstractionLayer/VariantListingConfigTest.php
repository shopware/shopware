<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;

/**
 * @internal
 */
#[CoversClass(VariantListingConfig::class)]
class VariantListingConfigTest extends TestCase
{
    public function testInstantiate(): void
    {
        $displayParent = true;
        $mainVariantId = '1';
        $configuratorGroupConfig = ['key' => 'value'];

        $variantListingConfig = new VariantListingConfig($displayParent, $mainVariantId, $configuratorGroupConfig);

        static::assertSame($displayParent, $variantListingConfig->getDisplayParent());
        static::assertSame($mainVariantId, $variantListingConfig->getMainVariantId());
        static::assertSame($configuratorGroupConfig, $variantListingConfig->getConfiguratorGroupConfig());
    }
}
