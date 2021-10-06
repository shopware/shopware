---
title: Catch NotFoundException when copying assets to GCP bucket
issue: NEXT-17739
author: Stefan van Essen
author_email: git@stefan-van-essen.nl
author_github: eXistenZNL
---
# Core
* Added a try/catch block to `Shopware\Core\Framework\Plugin\Util\AssetService::copyAssetsFromBundle()` to prevent an uncaught exception from bubbling up
