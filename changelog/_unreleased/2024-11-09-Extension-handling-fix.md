---
title: Extension handling fix
issue: NEXT-00000
author: Gandalf Volker
author_email: gandalf@nuonic.de
author_github: g-volker
---
# CORE
* Changed the way the PromotionDeliveryCalculator works to fix the extension handling. Retrieves the extension
    from the old promotion LinteItem and adds it to the new promotion LineItem in order for it to not be created again later.
