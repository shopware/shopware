---
title: Fixed deletion of tagged media entities
issue: NEXT-13387
---
# Core
* Added `ON DELETE CASCADE` constraint to `fk.media_tag.media_id`, thus allowing deletion of tagged media entities.
