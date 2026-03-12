---
title: Fix race condition between cart persist and load
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Core
* Fixed a race condition where concurrent cart load and persist could overwrite or lose cart data. Cart load now sets the cart as persisted and the error hash; Redis cart persister uses `SET NX` semantics when creating and only updates existing keys when the cart was already persisted, and dispatches `CartSavedEvent` only after a successful persist.
