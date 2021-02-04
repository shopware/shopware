---
title: Add a new state machine transition for order delivery from partially returned to returned
issue: NEXT-11304
---
# Core
*  Added a new record in `state_machine_transition` table with `action_name` = 'retour' from a partially returned state to returned state for an order delivery state.
