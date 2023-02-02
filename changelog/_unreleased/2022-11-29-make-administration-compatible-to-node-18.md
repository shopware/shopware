---
title: Make administration compatible to node 18
issue: NEXT-23904
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added hack to webpack config to replace the crypto hash function with a "sha256" when "md4" is used
