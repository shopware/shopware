---
title: Allow deletion of users that changed order states
issue: NEXT-11335
---
# Core
*  Changed the ForeignKey constraint from `state_machine_history` table to `user` table to set the `user_id` to null deletion of the user, thus allowing the deletion of users that changed the state of orders.
