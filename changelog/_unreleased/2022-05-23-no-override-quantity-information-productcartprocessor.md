---
title: No Override of the QuantityInformation inside the ProductCartProcessor
issue: 
flag: 
author: Patrick Thimm
author_email: patrick.thimm@sgs.com
author_github: @ufcyg
---

# Core

-   Changed `enrich()` inside `ProductCartProcessor.php` to only create a QuantityInformation object if necessary to prevent overriding existing settings
