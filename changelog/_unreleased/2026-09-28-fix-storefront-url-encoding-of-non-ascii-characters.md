---
title: Fix storefront URL encoding of non-ASCII characters
issue: 20681
---
# Storefront
* Changed `Shopware\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter::encodeUrl()` (Twig filters `sw_encode_url` and `sw_encode_media_url`) to parse URLs with `GuzzleHttp\Psr7\Uri` instead of `parse_url()`. On platforms whose libc treats bytes 0x80-0x9F as control characters, `parse_url()` replaced them with `_`, corrupting characters such as `Ä`, `Ö`, `Ü` and `ß` (e.g. `%C3_`).
* Changed `encodeUrl()` to decode the path before encoding it segment by segment, so already encoded URLs are no longer double encoded. User info in the authority is now kept.
