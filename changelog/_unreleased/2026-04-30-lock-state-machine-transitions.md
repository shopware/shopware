---
title: Lock state machine transitions
issue: #16183
---
# Core
* Added an internal `StateMachineLocker` for locking state machine transitions.
* Changed `StateMachineRegistry::transition()` to wait on a lock per entity and context version before writing the state transition to prevent duplicate history entries during concurrent transition requests.
