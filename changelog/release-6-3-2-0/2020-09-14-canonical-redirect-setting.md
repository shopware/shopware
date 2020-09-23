---
title: Allow indicating canonical URLs with a redirect (HTTP 301) instead of a link
issue: NEXT-6753
author: Philip Gatzka
author_email: p.gatzka@shopware.com
author_github: philipgatzka
---
# Core
* Added the `\Shopware\Core\Framework\Routing\CanonicalRedirectService` which is responsible for determining, wether a
  redirect response needs to be sent.
* Changed method `doHandle()` of the `\Shopware\Core\HttpKernel` to use the `CanonicalRedirectService` to decide wether
  all preconditions for a redirect are met.
* Added `\Shopware\Core\Framework\Event\BeforeSendRedirectResponseEvent` which is fired by the `doHandle()` method of
  the `\Shopware\Core\HttpKernel` just before sending a redirect response.
___
# Administration
* Changed `src/module/sw-settings-seo/page/sw-settings-seo/sw-settings-seo.html.twig` to include a switch which allows
  administrators to select, wether a redirect or a `<link rel="canonical">` should be used to tag superseded SEO URLs.
