---
title: Fix changeset issues
issue: NEXT-00000
author: Jasper Peeters
author_email: jasper.peeters@meteor.be
author_github: JasperP98
---

# Core

* Fixed issue with changeset where two null values were being seen as a change due to type casting
* Fixed issue with changeset where a JsonUpdateCommand for the same entity, will replace the previous changeset instead of merging it
