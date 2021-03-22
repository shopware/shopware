---
title: Fix admin search for searching complete email
issue: NEXT-10672
---
# Core
* Added a new property `tokenize` (default true) in `\Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking` flag
* Added a new conditional check in `\Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder::buildScoreQueries` to skip scoring queries for term parts if the SearchRanking flag is false
* Changed EmailField in `CustomerDefinition` to mark this field's SearchRanking flag as non tokenize to be able searching customer email as a whole
