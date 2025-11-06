---
title: Fix SalesChannelContext::state to reset to previous state
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added overwrite for `StateAwareTrait::state` to `SalesChannelContext` forwarding to inner context like the other `StateAwareTrait` to ensure closure keeps all previous states and resets to previous state 
