---
title: Fix plugin chunk hash
issue: NEXT-34214
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed the plugin chunk name to be the hash of the chunk content. This way the chunk name will change if the content changes and the browser will request the new chunk.
