---
title: Fix overridden super calls in promise chains in admin components
issue: NEXT-30599
author: Maximilian Rüsch
author_email: maximilian.ruesch@pickware.de
author_github: maximilianruesch
---
# Administration
* Changed the `async-component.factory.ts` to allow overrides of components that make `$super` calls inside promise chains.
