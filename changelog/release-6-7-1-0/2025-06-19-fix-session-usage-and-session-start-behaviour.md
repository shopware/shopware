---
title: Fix session usage and session start behaviour
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Added `FALLBACK_SESSION_NAME` to `PlatformRequest` to unify the fallback name
___
# Storefront
* Changed `Framework\Cookie\CookieProvider` to correctly use the session name from the session options instead of a hardcoded string
* Changed `Framework\Routing\StorefrontSubscriber` to no longer modify the session name
