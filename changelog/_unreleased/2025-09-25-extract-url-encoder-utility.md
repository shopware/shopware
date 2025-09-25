---
title: Extract url encoder utility
issue: 12573
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: @larskemper
---
# Core
* Added `UrlEncoder` utility class in Core with static `encodeUrl()` method
* Changed `MediaSerializer` to use `UrlEncoder::encodeUrl()` instead of injecting `UrlEncodingTwigFilter`
___
# Storefront
* Changed `UrlEncodingTwigFilter` to delegate to `UrlEncoder::encodeUrl()` for consistent behavior
