---
title: ProductListingFeaturesSubscriber.php
issue: NEXT-23654
author: AnimeGuru
author_email: melanityt@gmail.com
author_github: AnimeGuru
---
# Core
* Changed `handleSorting()` in `Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber` to include a Fallback Sorting Field for each Sorting preventing undeterministic behaviour with ORDER BY with entries of the same value when using OFFSET (e.g. Pagination) in the Listing.
___
# Upgrade Information
## Before
```php
private function handleSorting(Request $request, Criteria $criteria, SalesChannelContext $context): void
{
    /** @var ProductSortingCollection $sortings */
    $sortings = $criteria->getExtension('sortings') ?? new ProductSortingCollection();
    $sortings->merge($this->getAvailableSortings($request, $context->getContext()));

    $currentSorting = $this->getCurrentSorting($sortings, $request);

    $criteria->addSorting(
        ...$currentSorting->createDalSorting()
    );

    $criteria->addExtension('sortings', $sortings);
}
```
## After
```php
private function handleSorting(Request $request, Criteria $criteria, SalesChannelContext $context): void
{
    /** @var ProductSortingCollection $sortings */
    $sortings = $criteria->getExtension('sortings') ?? new ProductSortingCollection();
    $sortings->merge($this->getAvailableSortings($request, $context->getContext()));

    $currentSorting = $this->getCurrentSorting($sortings, $request);

    $dalSorting = $currentSorting->createDalSorting();
    $dalSorting[] = $this->createFallbackSorting();

    $criteria->addSorting(
        ...$dalSorting
    );

    $criteria->addExtension('sortings', $sortings);
}

(...)

/** This ensures deterministic behaviour with duplicate keys in ORDER BY and OFFSET between queries */
private function createFallbackSorting(): FieldSorting {
    return new FieldSorting(
        'product.id',
        FieldSorting::ASCENDING,
        false
    );
}
```
