---
title: Fix state machine history FK constraint to integration
issue: #11583
---
# Core
* Changed `ON DELETE` action for foreign key `fk.state_machine_history.integration_id` on `state_machine_history`table to `SET NULL` to prevent constraint violation when deleting an integration that has added state machine history entities.
