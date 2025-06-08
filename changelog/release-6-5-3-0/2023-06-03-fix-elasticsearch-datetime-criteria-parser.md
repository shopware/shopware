---
title: Fix Elasticsearch DateTime criteria parser
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed DateTime format of the Elasticsearch query in the `Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser` to ignore milliseconds and fix them to `000`
