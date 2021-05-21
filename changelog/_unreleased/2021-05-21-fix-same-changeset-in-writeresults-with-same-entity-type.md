---
title: Fixes calculating an equivalent ChangeSet for multiple WriteResults
author: Maximilian Ruesch
author_email: maximilian.ruesch@pickware.de
---
# Core
* The matching of the new `state` of the entity to the `WriteCommand` is fixed by using additional checks if relevant
  info of the objects matches.
