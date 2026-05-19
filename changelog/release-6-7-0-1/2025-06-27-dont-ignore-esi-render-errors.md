---
title: Don't ignore ESI render errors
---
# Storefront
* Changed `base.html.twig` to not ignore ESI render errors for the header and footer. This means that if an ESI tag fails to render, it will now throw an error instead of silently failing. This change is intended to help developers identify and fix issues with ESI includes more easily.
___
# Upgrade Information
## ESI render errors are not ignored anymore
When rendering the `/header` and `/footer` ESI tags, errors will now be thrown instead of ignored. This change is intended to help developers identify and fix issues with ESI includes more easily.
If you want to keep the old behaviour you need to overwrite the template blocks and remove the `ignore_errors: false` option from the `render_esi` function call.
