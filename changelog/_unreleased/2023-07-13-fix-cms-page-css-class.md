---
title: Fix cms page won't add custom css classes.
issue: NEXT-29203
author: Mario Schierhoff
author_email: DerKaito99@gmail.com
author_github: @derkaito
---
# Storefront
* fix `storefront/page/content/index.html.twig` to get cssClass from cmsPage variable instead of global page.landingPage.
