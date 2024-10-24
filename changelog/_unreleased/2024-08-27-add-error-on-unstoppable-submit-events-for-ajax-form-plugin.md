---
title: Add error on unstoppable submit events, that should be handled by form-ajax-submit plugin
issue: NEXT-37427
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Storefront
* Added check and error log in `form-ajax-submit.plugin.js` to not handle events, that are not cancable.
