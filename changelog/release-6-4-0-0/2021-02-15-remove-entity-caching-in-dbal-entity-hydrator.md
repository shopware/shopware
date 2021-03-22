---
title: Remove entity caching in DBAL Entity Hydrator
issue: NEXT-13852
author: Alexander Bachmann
author_email: email.bachmann@gmail.com
author_github: AlexBachmann
---
# Core
* Removed entity caching in DBAL Entity Hydrator to avoid conflicts when hydrating the same identity with differently loaded associations
