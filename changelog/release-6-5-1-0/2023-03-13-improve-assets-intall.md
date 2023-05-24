---
title: Improve assets install
issue: NEXT-25068
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Core
* Changed `Shopware\Core\Framework\Plugin\Util\AssetService` to upload a manifest file with hashes of each asset, further asset syncs use the manifest and only upload/delete the necessary files.
