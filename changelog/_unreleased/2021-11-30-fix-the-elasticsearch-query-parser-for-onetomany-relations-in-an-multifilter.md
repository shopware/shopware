---
title: Fix the Elasticsearch Query Parser for OneToMany-Relations in an MultiFilter
issue: NEXT-17324
author: Simon Vorgers
author_email: s.vorgers@shopware.com
author_github: SimonVorgers
---
# Core
* Changed `Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser` to build an And-MultiFilter with OneToMany-Relations correctly.