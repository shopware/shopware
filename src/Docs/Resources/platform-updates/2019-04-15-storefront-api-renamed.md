[titleEn]: <>(Storefront-API is now SalesChannel-API)

For plausibility reasons we removed the Storefront term from the core bundles and named it SalesChannel. The idea beeing:

The Core knows about sales channels and exposes an API for SalesChannels. All customer facing applications then connect to this SalesChannel-API.Doesn't matter whether its a fully featured store front, a buy button, something with voice or whatever.

Therefore:

* All former storefront-controllers now reside in a `SalesChannel` Namespace as `SalesChannel` controllers
* The api is now under `.../sales-channel-api/...`
* A test exists to secure this
