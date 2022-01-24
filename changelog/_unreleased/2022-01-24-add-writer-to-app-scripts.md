---
title: Add writer to custom endpoint AppScripts
issue: NEXT-19487
---
# Core
* Added `\Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacade` to provide `write` functionality to app scripts.
* Changed `\Shopware\Core\Framework\Script\Api\ApiHook` and `\Shopware\Core\Framework\Script\Api\StoreApiHook` to provide access to the new `writer` service.
