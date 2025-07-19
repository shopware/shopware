---
title: Update context languageIdChain types
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\Framework\Context` to only accept a `languageIdChain` parameter of type `non-empty-list<string>` to prevent passing empty lists
