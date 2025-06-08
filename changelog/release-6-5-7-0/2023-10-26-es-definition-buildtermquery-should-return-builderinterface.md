---
title: ES Definition buildTermQuery should return BuilderInterface
issue: NEXT-30186
author: thuong.le
author_email: t.le@shopware.com
author_github: thuong.le
---
# Core
* Deprecated `buildTermQuery` method in `\Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition` class that will return BuilderInterface from next major version.
___
# Next Major Version Changes
## ES Definition's buildTermQuery could return BuilderInterface:
* In 6.5 we only allow return `BoolQuery` from `AbstractElasticsearchDefinition::buildTermQuery` method which is not always the case. From next major version, we will allow return `BuilderInterface` from this method.
