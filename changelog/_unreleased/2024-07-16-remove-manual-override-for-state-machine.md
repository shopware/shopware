---
title: Remove code override for state machine
issue: NEXT-10502
flag: V6_7_0_0
author: 0x4
author_email: 0xVier@gmail.com
author_github: @0x4
---
# Core
* Changed `Shopware\Core\System\StateMachine\StateMachineRegistry` to not make any state cancellable in the code.
___
# Next Major Version Changes
## Removed code that made every state cancellable in the code:
* Changed `Shopware\Core\System\StateMachine\StateMachineRegistry` to not make any state cancellable. Currently, all states can be cancelled by default. The state machine will now only follow the state flow as specified in the database.
