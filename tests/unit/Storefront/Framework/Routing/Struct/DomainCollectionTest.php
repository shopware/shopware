<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Routing\Struct\DomainCollection;
use Shopware\Storefront\Framework\Routing\Struct\DomainStruct;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DomainCollection::class)]
class DomainCollectionTest extends TestCase
{
    public function testAddKeysTheDomainByItsUrlWithATrailingSlash(): void
    {
        $domain = DomainStruct::fromArray(self::row('https://example.com/de/'));

        $collection = new DomainCollection();
        $collection->add($domain);

        static::assertSame($domain, $collection->get('https://example.com/de/'));
    }

    public function testFromArrayBuildsOneDomainPerRow(): void
    {
        $collection = DomainCollection::fromArray([
            'https://example.com/' => self::row('https://example.com'),
            'https://example.com/en/' => self::row('https://example.com/en'),
        ]);

        static::assertCount(2, $collection);
        static::assertInstanceOf(DomainStruct::class, $collection->get('https://example.com/'));
        static::assertInstanceOf(DomainStruct::class, $collection->get('https://example.com/en/'));
    }

    public function testGetApiAlias(): void
    {
        static::assertSame('storefront_domain_collection', (new DomainCollection())->getApiAlias());
    }

    /**
     * @return array<string, string|null>
     */
    private static function row(string $url): array
    {
        return [
            'url' => $url,
            'id' => 'domain-id',
            'salesChannelId' => 'sales-channel-id',
            'typeId' => 'type-id',
            'snippetSetId' => 'snippet-set-id',
            'currencyId' => 'currency-id',
            'languageId' => 'language-id',
            'maintenance' => '0',
            'locale' => 'en-GB',
        ];
    }
}
