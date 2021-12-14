---
title: Cookie Consent misbehaviour
issue: NEXT-18060
author: Niklas Limberg
author_email: n.limberg@shopware.com
---
# Storefront
* Changed `storefront/component/analytics.html.twig` and `google-analytics.plugin.js` to only import and instantiate the `googletagmanager` script if the user accepts it
