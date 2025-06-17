---
title: Revert "Silently ignore admin ES errors (NEXT-37382)"
author: Paul von Allwörden
author_email: paul.von.allwoerden@pickware.de
author_github: @paulvonallwoerden
---

# Core
* Changed `Shopware\Elasticsearch\Admin\AdminSearchRegistry` to throw open search exceptions during indexing so that the triggering message queue message is correctly marked as a failure.
