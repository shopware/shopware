---
title: Add separator to admin ES search indexer queries
author: Marcus Müller
author_email: 25648755+M-arcus@users.noreply.github.com
author_github: @M-arcus
issue: NEXT-32814
---
# Core
* Added separator `SEPARATOR " "` to admin ES search indexer SQL queries that use `GROUP_CONCAT` to avoid search issues
