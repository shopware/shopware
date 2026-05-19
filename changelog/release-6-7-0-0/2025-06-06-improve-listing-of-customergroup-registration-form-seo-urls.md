---
title: Improve listing of customerGroup registration form SEO URLs
issue: #10192
author: Benedikt Schulze Baek
author_email: benedikt@schulze-baek.de
author_github: @bschulzebaek
---
# Administration
* Deprecated `seoUrlRepository`, `seoUrlCriteria`, `loadSeoUrls` and `getSeoUrl` in `src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail`. SEO URLs are handled via associations.
