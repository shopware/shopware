---
title: Fix affiliate and campaign code storage
issue: NEXT-5790
author: Devin AI
author_email: devin-ai-integration[bot]@users.noreply.github.com
author_github: @devin-ai-integration
---
# Core
*  
___
# API
*  
___
# Administration
*  
___
# Storefront
* Added new plugin class `affiliate-tracking.plugin.js` to store affiliate and campaign codes in cookies
* Deprecated `AffiliateTrackingListener` class (to be removed in v6.8.0)
___
# Upgrade Information
## Affiliate and Campaign Tracking
The affiliate and campaign codes are now stored in cookies in addition to the session. This ensures that the codes persist across different browser sessions and visits.
___
# Next Major Version Changes
## Affiliate and Campaign Tracking
The `AffiliateTrackingListener` class will be removed in v6.8.0. Use the cookie-based approach instead.
