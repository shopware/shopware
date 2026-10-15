<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\StorefrontContextMappingCandidateProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\Test\Generator;
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

    #[TestDox('resolves an approved currency value from the Storefront context')]
    public function testResolvesCurrencyIsoCodeFromSalesChannelContext(): void
    {
        $provider = new StorefrontContextMappingCandidateProvider();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('USD');
        $salesChannelContext = Generator::generateSalesChannelContext(currency: $currency);
        $source = new MappingSourceReference('context', 'storefront', path: 'currency.isoCode');

        $resolved = $provider->resolveSource(
            $source,
            new MappingSourceResolutionContext(
                StoredElementBuilder::create('core:text', 'element-1')->build(),
                [],
                [],
                $salesChannelContext,
                null,
                null,
            ),
        );

        static::assertSame('USD', $resolved);
    }
}
