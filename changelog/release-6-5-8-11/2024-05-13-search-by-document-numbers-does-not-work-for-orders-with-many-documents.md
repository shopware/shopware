---
title: Search by document numbers does not work for orders with many documents
issue: NEXT-29683
---
# Core
* Changed `src/Core/Framework/DataAbstractionLayer/Dbal/CriteriaQueryBuilder.php` to add conditions for search queries to fix the counting scores issue.
