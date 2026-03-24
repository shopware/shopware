---
title: Fix race condition between cart persist and load
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Core
* Changed `CartPersister` and `RedisCartPersister` to only insert a cart if it is new, otherwise only update it to avoid a race condition where concurrent cart load and persist could overwrite or lose cart data.
