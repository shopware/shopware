---
title: Disallow empty id filters in Criteria
issue: NEXT-34765
author: Benedikt Brunner
author_email: benedikt.brunner@pickware.de
author_github: Benedikt-Brunner
---
# Core
*  Added check to disallow empty id filters when constructing `Criteria`, to avoid accidental loading of all entries from the database.

