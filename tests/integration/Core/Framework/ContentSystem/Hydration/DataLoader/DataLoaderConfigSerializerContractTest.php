<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\ContentSystem\DataLoader\PaymentMethodDataLoader;
use Shopware\Core\Checkout\Shipping\ContentSystem\DataLoader\ShippingMethodDataLoader;
use Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbDataLoader;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationDataLoader;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\ServiceMenuDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\CrossSellingDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSearchDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSuggestDataLoader;
use Shopware\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Currency\ContentSystem\DataLoader\CurrencyDataLoader;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageDataLoader;
use Shopware\Core\Test\Stub\ContentSystem\TestMultiReferenceGatingLoader;
use Shopware\Core\Test\Stub\ContentSystem\TestNavigationShapedLoader;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Enforces the round-trip contract documented on {@see AbstractContentDataLoaderConfigSerializer} for every
 * registered `content_system.config_serializer`: `encode(decode(x))` must be stable on the wire form, and
 * `encode()` must not diverge from the decoded config's `jsonSerialize()`. {@see AttributionReconciler} relies
 * on both configs it compares producing the same canonicalized `encode(decode(...))` output for the same
 * authored wiring, so a serializer that violates this contract silently drops an attribution that is still
 * honest.
 *
 * @internal
 */
#[Package('framework')]
class DataLoaderConfigSerializerContractTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideConfigsPerSource')]
    #[TestDox('encode(decode(x)) is stable and equals decode(x)->jsonSerialize()')]
    public function testRoundTripContractHoldsPerSource(string $source, array $config): void
    {
        $provider = $this->provider();

        $decoded = $provider->decode($source, $config);
        $encoded = $provider->encode($source, $decoded);

        static::assertSame(
            $decoded->jsonSerialize(),
            $encoded,
            \sprintf('encode() must not diverge from jsonSerialize() for source "%s".', $source),
        );

        $reEncoded = $provider->encode($source, $provider->decode($source, $encoded));

        static::assertSame(
            $encoded,
            $reEncoded,
            \sprintf('encode(decode(x)) must be stable on the wire form for source "%s".', $source),
        );
    }

    #[TestDox('the fixture set covers every registered content_system.config_serializer source')]
    public function testFixturesCoverEveryRegisteredSource(): void
    {
        $registered = array_keys($this->registeredSources());
        $fixtured = array_keys(iterator_to_array(self::provideConfigsPerSource()));

        sort($registered);
        sort($fixtured);

        static::assertSame(
            $registered,
            $fixtured,
            'A source is registered as a content_system.config_serializer but has no round-trip contract '
            . 'fixture, or vice versa. Missing fixture(s): ' . implode(', ', array_diff($registered, $fixtured))
            . '. Missing registration(s): ' . implode(', ', array_diff($fixtured, $registered)),
        );
    }

    /**
     * @return iterable<string, array{source: string, config: array<string, mixed>}>
     */
    public static function provideConfigsPerSource(): iterable
    {
        yield NavigationDataLoader::SOURCE => [
            'source' => NavigationDataLoader::SOURCE,
            'config' => ['rootId' => 'main-navigation', 'depth' => 3, 'activeProperty' => 'customActiveId'],
        ];
        yield ServiceMenuDataLoader::SOURCE => [
            'source' => ServiceMenuDataLoader::SOURCE,
            'config' => ['rootId' => 'custom-service-root'],
        ];
        yield BreadcrumbDataLoader::SOURCE => [
            'source' => BreadcrumbDataLoader::SOURCE,
            'config' => ['property' => 'entityId', 'type' => 'category', 'referrerCategoryProperty' => 'refCategoryId'],
        ];
        yield ProductListingDataLoader::SOURCE => [
            'source' => ProductListingDataLoader::SOURCE,
            'config' => ['property' => 'navigationId', 'associations' => ['manufacturer']],
        ];
        yield CrossSellingDataLoader::SOURCE => [
            'source' => CrossSellingDataLoader::SOURCE,
            'config' => ['property' => 'productId', 'associations' => ['media']],
        ];
        yield ProductReviewDataLoader::SOURCE => [
            'source' => ProductReviewDataLoader::SOURCE,
            'config' => ['property' => 'productId', 'associations' => ['customerReview']],
        ];
        yield ProductSearchDataLoader::SOURCE => [
            'source' => ProductSearchDataLoader::SOURCE,
            'config' => ['searchTermProperty' => 'searchTerm', 'associations' => ['manufacturer']],
        ];
        yield ProductSuggestDataLoader::SOURCE => [
            'source' => ProductSuggestDataLoader::SOURCE,
            'config' => ['searchTermProperty' => 'searchTerm', 'associations' => ['manufacturer']],
        ];
        yield PaymentMethodDataLoader::SOURCE => [
            'source' => PaymentMethodDataLoader::SOURCE,
            'config' => ['associations' => ['media'], 'onlyAvailable' => false],
        ];
        yield CurrencyDataLoader::SOURCE => [
            'source' => CurrencyDataLoader::SOURCE,
            'config' => ['associations' => ['country']],
        ];
        yield LanguageDataLoader::SOURCE => [
            'source' => LanguageDataLoader::SOURCE,
            'config' => ['associations' => ['locale']],
        ];
        yield ShippingMethodDataLoader::SOURCE => [
            'source' => ShippingMethodDataLoader::SOURCE,
            'config' => ['associations' => ['deliveryTime'], 'onlyAvailable' => false],
        ];
        yield EntityLoader::SOURCE => [
            'source' => EntityLoader::SOURCE,
            'config' => ['entity' => 'product', 'property' => 'name', 'associations' => ['manufacturer']],
        ];
        yield EntityCollectionLoader::SOURCE => [
            'source' => EntityCollectionLoader::SOURCE,
            'config' => ['entity' => 'product', 'property' => 'name', 'associations' => ['manufacturer']],
        ];
        yield TestMultiReferenceGatingLoader::SOURCE => [
            'source' => TestMultiReferenceGatingLoader::SOURCE,
            'config' => ['entity' => 'media', 'property' => 'mediaId', 'secondProperty' => 'captionMediaId', 'activeProperty' => 'activeId'],
        ];
        yield TestNavigationShapedLoader::SOURCE => [
            'source' => TestNavigationShapedLoader::SOURCE,
            'config' => ['entity' => 'media', 'activeProperty' => 'activeId'],
        ];
    }

    private function provider(): DataLoaderConfigSerializerProvider
    {
        $provider = static::getContainer()->get(DataLoaderConfigSerializerProvider::class);
        static::assertInstanceOf(DataLoaderConfigSerializerProvider::class, $provider);

        return $provider;
    }

    /**
     * @return array<string, mixed>
     */
    private function registeredSources(): array
    {
        $locator = (new \ReflectionProperty(DataLoaderConfigSerializerProvider::class, 'locator'))->getValue($this->provider());
        static::assertInstanceOf(ServiceLocator::class, $locator);

        return $locator->getProvidedServices();
    }
}
