---
title: Improve StateMachineHistory query performance
issue: NEXT-18904
author: Michel Bade
author_email: m.bade@shopware.com
author_github: @cyl3x
---
# Core
* Deprecated `entity_id` column in favour for the new `referenced_id` and `referenced_version_id` columns for v6.6.0.0
* Added `referenced_id` and `referenced_version_id` columns generated from `entity_id` to `state_machine_history`
