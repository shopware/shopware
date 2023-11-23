---
title: Use AbstractTokenFilter in StopwordTokenFilterr
issue: NEXT-31263
---
# Core
* Changed dependency of `\Shopware\Elasticsearch\Product\StopwordTokenFilter` from `\Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter` to `\Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter` to allow token filter decorators works when adding elasticsearch bundle
