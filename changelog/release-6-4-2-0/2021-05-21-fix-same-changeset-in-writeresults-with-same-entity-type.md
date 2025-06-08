---
title: Fixes calculating an equivalent ChangeSet for multiple WriteResults
issue: NEXT-15473
author: Maximilian Ruesch
author_email: maximilian.ruesch@pickware.de
---
# Core
* Added additional checks to the `EntityWriteGateway` on matching of relevant info of the object to fix the matching of the new `state` of the entity to the `WriteCommand` 
