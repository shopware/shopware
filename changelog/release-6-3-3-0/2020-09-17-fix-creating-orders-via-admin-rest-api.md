---
title: Fix creating orders via admin REST API
issue: NEXT-10842, NEXT-7800
author: Manuel Kress
author_email: 6232639+windaishi@users.noreply.github.com 
author_github: windaishi
---
# Core
* The creation of an `order` entity does not result in an error regarding the write-protection of the `stateId` field anymore.
___
# Upgrade Information
## Write protection of `StateMachineStateField` was removed
The `StateMachineStateField` does not have a write-protection by default anymore. Instead, the scopes which are allowed
to write the field directly have to be given as a constructor parameter of the `StateMachineStateField` class.
