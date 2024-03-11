---
title: Add entity index to state machine history
issue: NEXT-31874
author: Maximilian Rüsch
author_email: maximilian.ruesch@pickware.de
author_github: maximilianruesch
---
# Core
* Added a migration to add an index to the `state_machine_history` table to improve performance of state history searches.
