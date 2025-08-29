---
title: Support full-page caches in affiliate tracking feature
issue: 5790
author: Niklas Wolf
author_github: @niklaswolf
---
# Storefront
* Added asynchronous affiliate code tracking to support the feature also behind full page caches
* Removed AffiliateTrackingListener as it is replaced by the async tracking
___
# Upgrade Information
## Changes to how affiliate tracking works
The affiliate tracking feature has been changed to support full page caches. The tracking is now triggered 
asynchronously via JavaScript and not directly on the initial request anymore. If your plugin or custom code has 
some functionality that also writes to the session triggered asynchronously (via AJAX), you might use the new custom 
event `affiliateTrackingDone` emitted by the new `AffiliateTracking` Javascript plugin. This ensures that there are no 
race conditions between multiple asynchronous writes to the session potentially leading to lost data.
