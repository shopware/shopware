---
title: Warn in rules when DPG indexing is disabled
issue: 12354
author: Nikolas Evers
author_email: n.evers@shopware.com
author_github: vintagesucks
---
# Administration
* Added a warning banner to rule detail pages when dynamic product group (DPG) indexing is disabled and the rule contains "Item in dynamic product group" conditions, since these conditions will always evaluate to false while indexing is disabled.
