<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Aggregate\SalesChannelType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationCollection;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelTypeEntity::class)]
class SalesChannelTypeEntityTest extends TestCase
{
    public function testAccessorsRoundTrip(): void
    {
        $type = new SalesChannelTypeEntity();

        $salesChannels = new SalesChannelCollection();
        $translations = new SalesChannelTypeTranslationCollection();

        $type->setName('Storefront');
        $type->setManufacturer('shopware AG');
        $type->setDescription('Sales channel with HTML storefront');
        $type->setDescriptionLong('Sales channel with HTML storefront.');
        $type->setCoverUrl('https://example.com/cover.png');
        $type->setIconName('default-building-shop');
        $type->setScreenshotUrls(['https://example.com/screenshot.png']);
        $type->setSalesChannels($salesChannels);
        $type->setTranslations($translations);

        static::assertSame('Storefront', $type->getName());
        static::assertSame('shopware AG', $type->getManufacturer());
        static::assertSame('Sales channel with HTML storefront', $type->getDescription());
        static::assertSame('Sales channel with HTML storefront.', $type->getDescriptionLong());
        static::assertSame('https://example.com/cover.png', $type->getCoverUrl());
        static::assertSame('default-building-shop', $type->getIconName());
        static::assertSame(['https://example.com/screenshot.png'], $type->getScreenshotUrls());
        static::assertSame($salesChannels, $type->getSalesChannels());
        static::assertSame($translations, $type->getTranslations());
    }

    public function testUnsetOptionalValuesFallBackToEmptyValues(): void
    {
        $type = new SalesChannelTypeEntity();

        static::assertSame('', $type->getCoverUrl());
        static::assertSame('', $type->getIconName());
        static::assertSame([], $type->getScreenshotUrls());
    }
}
