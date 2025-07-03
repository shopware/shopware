---
title: Fix authentication for document downloads via the Store API
author: Justus Geramb
author_email: justus@devite.io
author_github: @jgeramb
---
# Core
* Removed context validation defaults in route annotation of `DocumentRoute::download` to allow all authentication checks to be performed by the `DocumentRoute::checkAuth` method, enabling authentication of guest customers with email and zip code of the billing address
