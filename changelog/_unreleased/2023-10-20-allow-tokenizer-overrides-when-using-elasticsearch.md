---
title: Allow tokenizer decorators wihth elasticsearch package
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
issue: NEXT-31263
---
# Core
* Changed dependency of `\Shopware\Elasticsearch\Product\ProductSearchQueryBuilder` from `\Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer` to `\Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface` to allow tokenizer decorators work when adding elasticsearch bundle
