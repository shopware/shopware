---
title: Fix partial hydreateEntity bug
author: Fabian Boensch
issue: NEXT-38028
author_github: @En0Ma1259
---
# Core
* Changed isPartial check in hydrateEntity. Bug with manyToOne Associations. "Real" Entity vs PartialEntity
