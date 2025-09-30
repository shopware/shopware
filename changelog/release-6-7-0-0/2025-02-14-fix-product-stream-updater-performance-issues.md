---
title: Fix ProductStreamUpdater performance issues
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater` to improve performance, reduce database access and duplicate product stream id updates
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater` to remove useless reset sql
* Changed `src/Core/Profiling/Resources/views/Collector/db.html.twig` to fix invalid escape
