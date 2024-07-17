---
title: Only select used fields of `translation` tables
issue: NEXT-00000
author: Sven Münnich
author_email: sven.muennich@pickware.de
author_github: svenmuennich
---
# Core
* Changed generated database queries that join translation tables to select as few fields as possible to improve performance.
