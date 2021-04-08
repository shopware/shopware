---
title: Page load event on 404 error
issue: /
author: Rune Laenen
author_email: rune@laenen.me 
author_github: runelaenen
---
# Storefront
* Changed the `ErrorController` to use the ErrorPageLoader for every error and template.
* `PageLoadedEvents` will be triggered also when no 404 template is configured.
