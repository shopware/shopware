---
title: Clean entity schema cache key on framework schema generation
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\Framework\Api\Command\DumpSchemaCommand` to clear the `CachedEntitySchemaGenerator::CACHE_KEY` cache key before retrieving the entity schema to ensure up-to-date output
