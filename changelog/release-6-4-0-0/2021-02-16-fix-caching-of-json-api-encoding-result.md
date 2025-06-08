---
title: Fix caching of JsonApiEncodingResult
issue: NEXT-13701
author: Alexander Bachmann
author_email: email.bachmann@gmail.com
author_github: @AlexBachmann
---
# Core
* Changed caching strategy in JsonApiEncodingResult for the `included` section. Use a merging strategy for existing entities.
