---
title: Remove objects from the storefront exception messages
issue: NEXT-30016
author: Ruslan Belziuk
author_email: ruslan@dumka.pro
author_github: @ruslanbelziuk
---
# Storefront
* Changed `StorefrontException.php` to prevent the context object from ruining the debug process with a very long JSON string
