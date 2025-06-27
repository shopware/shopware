---
title: Improve translator injection handling
issue: NEXT-00000
author: Jasper Peeters
author_email: jasper.peeters@meteor.be
author_github: JasperP98
---

# Core
* Changed: Added tracking flag to monitor translation injection state in `Shopware\Core\Framework\Adapter\Translation\Translator` to better manage when injections need to be reset
