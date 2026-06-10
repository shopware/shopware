# Out-of-range pagination on category/listing pages returns HTTP 200 instead of a redirect or 404 (SEO issue)

## Shopware Version

6.7.9.0

## Affected area / extension

Platform (Default)

## Actual behaviour

When a category or listing page is requested with a `p` query parameter that
exceeds the number of available pages for the current criteria (e.g.
`/Damen/?p=99` on a category with only 3 pages), Shopware responds with
**HTTP 200 OK** and renders the listing template with no products. From a search
engine's perspective the URL is a valid, indexable, empty page — the classic
"soft 404".

The store-api equivalent (`POST /store-api/product-listing/{categoryId}?p=99`)
likewise returns 200 with an empty listing.

## Expected behaviour

When the requested `p` is outside the valid range (`p < 1` or `p > lastPage`),
the server must respond with a non-200 status — for the store-api, **HTTP 404**
with error code `PRODUCT__LISTING_PAGE_OUT_OF_RANGE`.
