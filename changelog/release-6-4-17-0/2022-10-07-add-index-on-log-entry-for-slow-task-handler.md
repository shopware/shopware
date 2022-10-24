---
title: Add index on log entry for slow task handler
issue: NEXT-23615
author: Michiel Kalle
author_email: m.kalle@xsarus.nl
author_github: @michielkalle
___
# Core
* Add `idx.log_entry.created_at` on the `log_entry` table to fix slow `LogCleanupTaskHandler`.
