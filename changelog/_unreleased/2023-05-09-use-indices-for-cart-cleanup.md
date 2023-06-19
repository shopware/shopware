---
title: use-indices-for-cart-cleanup
issue: NEXT-23615
author: Robert Lang
author_github: @RobertLang
___
# Core
* Changed CleanupCartTaskHandler SQL to properly use `idx.cart.created_at` to select cart entries to be deleted.
