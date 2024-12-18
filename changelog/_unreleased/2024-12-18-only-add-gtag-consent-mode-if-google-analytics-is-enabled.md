---
title: Only add gtag consent mode if Google Analytics is enabled
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed `storefront/component/analytics.html.twig` template to only push Google consent if Google Analytics is activated
* Deprecated block `component_head_analytics_gtag_consent` from `storefront/component/analytics.html.twig`, use `component_head_analytics_gtag_google_consent` instead
___
# Next Major Version Changes
Change the block for the Google analytics/ads consent in the `storefront/component/analytics.html.twig` template:
```
{% block component_head_analytics_gtag_consent %}
```
to
```
{% block component_head_analytics_gtag_google_consent %}
```
and remove the definition of `window.dataLayer` and the function `gtag` from this block, if added
