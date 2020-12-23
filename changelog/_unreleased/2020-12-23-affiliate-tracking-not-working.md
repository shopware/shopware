---
title: Affiliate Tracking not working
issue: NEXT-11868
---
# Core
* Changed condition to add the affiliate or the campaign tracking params in the `checkAffiliateTracking` method of the `AffiliateTrackingListener` class, so that it can be cached in the http cache without having the both parameters.
* Changed condition of the `addAffiliateTracking` method in the `CheckoutController`, so that it can be added the affiliate or the campaign tracking params from the session to dataBag without having the both parameters.
* Changed condition of the `addAffiliateTracking` method in the `CartOrderRouter`, so that it can be added the affiliate or the campaign tracking params from the dataBag to cart without having  the both parameters.
