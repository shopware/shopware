---
title: Fix loading indicator in search suggest
issue: NEXT-17882
author: Sebastian König
author_email: s.koenig@tinect.de
author_github: @tinect
---
# Storefront
* Changed private method `_suggest` in `SearchWidgetPlugin` to abort client request before the loading indicator is created. This fixes the loading indicator to be removed when the search term has been changed.

