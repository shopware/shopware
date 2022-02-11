---
title: Log elasticsearch issues in any case
issue: NEXT-16905
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed order of statements in `logOrThrowException` in `src/Elasticsearch/Framework/ElasticsearchHelper.php` to always log exceptions
* Deprecated and renamed `Shopware\Elasticsearch\Framework\ElasticsearchHelper::logOrThrowException` to match new behaviour into `logAndThrowException`
