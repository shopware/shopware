<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\BuyerConsent\BuyerConsentExtension;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartCapability;
use Shopware\Core\Framework\Ucp\Capability\Catalog\CatalogLookupCapability;
use Shopware\Core\Framework\Ucp\Capability\Catalog\CatalogSearchCapability;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutCapability;
use Shopware\Core\Framework\Ucp\Capability\Discount\DiscountExtension;
use Shopware\Core\Framework\Ucp\Capability\Fulfillment\FulfillmentExtension;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\IdentityLinkingCapability;
use Shopware\Core\Framework\Ucp\Capability\Loyalty\LoyaltyExtension;
use Shopware\Core\Framework\Ucp\Capability\Order\OrderCapability;
use Shopware\Core\Framework\Ucp\Capability\UcpCapability;

/**
 * Pins capability `getSpecUrl()` outputs to the unversioned ucp.dev path
 * scheme (`https://ucp.dev/specification/<X>/`). The previous versioned
 * scheme (`/<date>/specification/<X>`) silently 404'd for capabilities the
 * UCP spec author had not yet promoted to a per-version page (cart,
 * catalog/search, catalog/lookup, loyalty). Switching to the unversioned
 * "latest" path keeps the discovery profile clickable for every capability
 * and is independent of which UCP version we declare in `UcpVersion::CURRENT`.
 *
 * @internal
 */
#[CoversNothing]
class CapabilitySpecUrlTest extends TestCase
{
    /**
     * @return iterable<string, array{UcpCapability, string}>
     */
    public static function provideCapabilities(): iterable
    {
        yield 'cart' => [new CartCapability(), 'https://ucp.dev/specification/cart/'];
        yield 'catalog.search' => [new CatalogSearchCapability(), 'https://ucp.dev/specification/catalog/search/'];
        yield 'catalog.lookup' => [new CatalogLookupCapability(), 'https://ucp.dev/specification/catalog/lookup/'];
        yield 'checkout' => [new CheckoutCapability(), 'https://ucp.dev/specification/checkout/'];
        yield 'order' => [new OrderCapability(), 'https://ucp.dev/specification/order/'];
        yield 'identity-linking' => [new IdentityLinkingCapability(), 'https://ucp.dev/specification/identity-linking/'];
        yield 'discount' => [new DiscountExtension(), 'https://ucp.dev/specification/discount/'];
        yield 'fulfillment' => [new FulfillmentExtension(), 'https://ucp.dev/specification/fulfillment/'];
        yield 'buyer-consent' => [new BuyerConsentExtension(), 'https://ucp.dev/specification/buyer-consent/'];
        // Loyalty is a Shopware-side extension capability — UCP has no per-page
        // spec for it, so the URL intentionally points at the reference page
        // where loyalty is mentioned in passing.
        yield 'loyalty' => [new LoyaltyExtension(), 'https://ucp.dev/specification/reference/'];
    }

    #[DataProvider('provideCapabilities')]
    public function testSpecUrlIsUnversionedAndPointsToAValidUcpSpecPath(
        UcpCapability $capability,
        string $expectedUrl
    ): void {
        $url = $capability->getSpecUrl();

        static::assertSame($expectedUrl, $url);
        static::assertStringStartsWith('https://ucp.dev/specification/', $url);
        static::assertStringEndsWith('/', $url, 'ucp.dev specification pages need the trailing slash to avoid a 301 round-trip.');
        static::assertDoesNotMatchRegularExpression(
            '@^https://ucp\.dev/\d{4}-\d{2}-\d{2}/@',
            $url,
            'Capability spec URLs must not embed a UCP version date — ucp.dev does not host per-version pages for all capabilities yet, and the unversioned path always resolves to the current spec.'
        );
    }
}
