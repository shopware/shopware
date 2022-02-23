---
title: Fix Google analytics tracking of gclid
issue: NEXT-18984
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Change handling of initializing the Google Tag Manager from the callback function to the corresponding JavaScript plugin to take the `gclid` into account if one navigates to a different page
* Add blocks `component_head_analytics_gtag` and `component_head_analytics_gtag_config` to the `@Storefront/storefront/component/analytics.html.twig` template
* Deprecate block `component_head_analytics_tag_config`
