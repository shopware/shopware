---
title: Track status changes performed by integrations
author: Felix Schneider
author_email: felix@wirduzen.de
author_github: @schneider-felix
---
# Core
* Added `integration_id` column to `state_machine_history`
* Changed `StateMachineRegistry::transition` to store integration id
___
# Administration
* Changed order ui to show which integrations performed an order state change
