<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext\StorefrontContextLoader;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\StorefrontContextMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StorefrontContextMappingCandidateProvider::class)]
class StorefrontContextMappingCandidateProviderTest extends TestCase
{
    #[TestDox('offers only the explicitly approved Storefront context values')]
    public function testOffersTheApprovedStorefrontContextAllowlist(): void
    {
        $candidates = (new StorefrontContextMappingCandidateProvider())->provide('product');

        static::assertSame([
            'context:storefront.currency.isoCode',
            'context:storefront.currency.symbol',
            'context:storefront.language.localeCode',
            'context:storefront.tax.state',
        ], array_map(static fn ($candidate): string => $candidate->path, $candidates));
        static::assertSame(['string'], array_values(array_unique(array_map(static fn ($candidate): string => $candidate->valueType, $candidates))));
    }

    #[TestDox('resolves an approved currency value from the element loader result')]
    public function testResolvesCurrencyIsoCodeFromLoaderValue(): void
    {
        $provider = new StorefrontContextMappingCandidateProvider();
        $source = new MappingSourceReference('context', 'storefront', path: 'currency.isoCode');

        $resolved = $provider->resolveSource(
            $source,
            new MappingSourceResolutionContext(
                StoredElementBuilder::create('core:text', 'element-1')->build(),
                [],
                [StorefrontContextLoader::REQUIREMENT_KEY => new ArrayStruct(['currency.isoCode' => 'USD'])],
                null,
                null,
                null,
            ),
        );

        static::assertTrue($resolved->hasValue);
        static::assertSame('USD', $resolved->value);
    }

    public function testMissingLoaderValueDoesNotReadTheSalesChannelContext(): void
    {
        $source = new MappingSourceReference('context', 'storefront', path: 'currency.isoCode');
        $resolved = (new StorefrontContextMappingCandidateProvider())->resolveSource(
            $source,
            new MappingSourceResolutionContext(StoredElementBuilder::create('core:text', 'element-1')->build(), [], [], null, null, null),
        );

        static::assertFalse($resolved->hasValue);
    }

    public function testOnlyBuiltInPathsClaimTheInternalLoader(): void
    {
        $provider = new StorefrontContextMappingCandidateProvider();

        static::assertTrue($provider->supportsSource(new MappingSourceReference('context', 'storefront', path: 'currency.isoCode')));
        static::assertFalse($provider->supportsSource(new MappingSourceReference('context', 'storefront', path: 'customer.email')));
        static::assertFalse($provider->supportsSource(new MappingSourceReference('context', 'storefront', ['custom' => true], 'currency.isoCode')));
        static::assertTrue(StorefrontContextMappingCandidateProvider::isApprovedDisplayName('context:storefront.tax.state'));
        static::assertFalse(StorefrontContextMappingCandidateProvider::isApprovedDisplayName('context:storefront.customer.email'));
    }
}
