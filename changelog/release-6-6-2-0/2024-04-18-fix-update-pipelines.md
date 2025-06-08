---
title: Fix / Update pipelines
issue: NEXT-00000
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `01-lint-admin` github action to build the missing `entity-schema-types` before the code check
* Changed `01-lint-storefront` github action to use the latest action releases
* Changed `01-php-lint` github action to use the latest action releases
* Changed `02-acceptance` github action to not run on pull requests
* Changed `02-unit` github action to use the latest action releases
* Changed `03-redis` github action to use the latest action releases
* Changed `ecs` config to include the lineEnding
* Changed `Migration1620820321AddDefaultDomainForHeadlessSaleschannelTest` to use `RunClassInSeparateProcess`
___
# Administration
* Removed the `.npmrc` file, because the node version is already validated by webpack
