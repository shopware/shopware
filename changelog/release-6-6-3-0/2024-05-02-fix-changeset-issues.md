---
title: Fix changeset issues
issue: NEXT-36289
author: Jasper Peeters
author_email: jasper.peeters@meteor.be
author_github: JasperP98
---

# Core

* Changed changeset detection to fix issue with changeset where two null values were being seen as a change due to type casting and fixed issue with changeset where a JsonUpdateCommand for the same entity, will replace the previous changeset instead of merging it
