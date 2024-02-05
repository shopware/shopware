---
title: typecast unsupported type in review filter
issue: NEXT-32102
---
# Storefront
* Changed `ProductReviewLoader::handlePointsAggregation` to typecast point parameter given from request to int.
