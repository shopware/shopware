---
title: Fixed deletion of tagged customer entities
issue: NEXT-12051
---
# Core
* Added `ON DELETE CASCADE` constraint to `fk.customer_tag.customer_id`, thus allowing deletion of tagged customer entities.
