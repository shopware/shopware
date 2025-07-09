---
title: Fix endless redirect loop when SEO URL contains query parameters
issue: 10833
---
# Core
* Fixed `SeoResolver::resolve()` method to properly handle query parameters in SEO URLs
* Query parameters are now separated from the path before database lookup to prevent redirect loops
* This resolves the issue where SEO URLs with query parameters like `Main-product/SWDEMO10001?test=123` would cause endless redirect loops