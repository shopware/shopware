<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Routing\Struct\DomainStruct;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DomainStruct::class)]
class DomainStructTest extends TestCase
{
    public function testFromArrayTrimsTrailingSlashesFromTheUrl(): void
    {
        $domain = DomainStruct::fromArray(self::row(['url' => 'https://example.com/de/']));

        static::assertSame('https://example.com/de', $domain->url);
    }

    public function testFromArrayMapsAllFields(): void
    {
        $domain = DomainStruct::fromArray(self::row([
            'themeId' => 'theme-id',
            'maintenanceIpAllowlist' => '["127.0.0.1"]',
            'themeName' => 'Storefront',
            'parentThemeName' => 'Base',
        ]));

        static::assertSame('domain-id', $domain->id);
        static::assertSame('sales-channel-id', $domain->salesChannelId);
        static::assertSame('type-id', $domain->typeId);
        static::assertSame('snippet-set-id', $domain->snippetSetId);
        static::assertSame('currency-id', $domain->currencyId);
        static::assertSame('language-id', $domain->languageId);
        static::assertSame('theme-id', $domain->themeId);
        static::assertSame('1', $domain->maintenance);
        static::assertSame('["127.0.0.1"]', $domain->maintenanceIpAllowlist);
        static::assertSame('en-GB', $domain->locale);
        static::assertSame('Storefront', $domain->themeName);
        static::assertSame('Base', $domain->parentThemeName);
    }

    public function testFromArrayKeepsUnsetOptionalFieldsNull(): void
    {
        $domain = DomainStruct::fromArray(self::row());

        static::assertNull($domain->themeId);
        static::assertNull($domain->maintenanceIpAllowlist);
        static::assertNull($domain->themeName);
        static::assertNull($domain->parentThemeName);
    }

    /**
     * @param array<string, string|null> $overrides
     *
     * @return array<string, string|null>
     */
    private static function row(array $overrides = []): array
    {
        return array_merge([
            'url' => 'https://example.com',
            'id' => 'domain-id',
            'salesChannelId' => 'sales-channel-id',
            'typeId' => 'type-id',
            'snippetSetId' => 'snippet-set-id',
            'currencyId' => 'currency-id',
            'languageId' => 'language-id',
            'maintenance' => '1',
            'locale' => 'en-GB',
        ], $overrides);
    }
}
