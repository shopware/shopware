---
title: Fix order generation in demodata
issue: NEXT-17712
author: Maximilian Ruesch
author_email: maximilian.ruesch@pickware.de
author_github: maximilianruesch
---
# Core
* Changed the `OrderGenerator` to set the shipping method in the `Cart` to the shipping method of the randomly selected sales channel.
