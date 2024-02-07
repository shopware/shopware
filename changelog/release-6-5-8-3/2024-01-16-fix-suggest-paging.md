---
title: Fix suggest paging
issue: NEXT-30573
author: oskroblin Skroblin
author_email: o.skroblin@shopware.com
---

# Core
* Changed `PagingListingProcessor` to also consider preset `limit` value when processing the request and apply defaults to the criteria
___
# Upgrade Information
## Paging processor now accepts preset limit
The `PagingListingProcessor` now also considers the preset `limit` value when processing the request. This means that the `limit` value from the request will be used if it is set, otherwise the preset `limit` value, of the provided criteria, will be used.
If the criteria does not have a preset `limit` value, the default `limit` from the system configuration will be used.

```
$criteria = new Criteria();
$criteria->setLimit(10);

$request = new Request();
$request->query->set('limit', 5);

$processor = new PagingListingProcessor();

$processor->process($criteria, $request);

// $criteria->getLimit() === 5
// $criteria->getLimit() === 10 (if no limit is set in the request)
```
