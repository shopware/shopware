<?php
// Excerpt from the fix PR #16575 regression test:
// tests/integration/Core/Content/Product/SalesChannel/Listing/ProductListingRouteTest.php
//
// createData() seeds a category + 6 products in the default Storefront sales
// channel. The out-of-range symptom fires with ANY product count, so a minimal
// repro needs only 1 product in 1 category (not the test's 6, and not demodata).

public function testReturnsHttpNotFoundWhenRequestedPageExceedsLastPage(): void
{
    $this->createData();

    $this->browser->request(
        'POST',
        '/store-api/product-listing/' . $this->ids->get('category') . '?p=99'
    );

    $response = $this->browser->getResponse();

    static::assertSame(404, $response->getStatusCode());

    $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    static::assertArrayHasKey('errors', $payload);
    static::assertSame('PRODUCT__LISTING_PAGE_OUT_OF_RANGE', $payload['errors'][0]['code']);
}
