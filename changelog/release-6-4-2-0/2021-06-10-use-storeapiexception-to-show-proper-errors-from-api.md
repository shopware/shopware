---
title: Use StoreApiException to show proper errors from API
issue: NEXT-15299
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Core
* Changed `Shopware\Core\Framework\Store\Services\ExtensionDownloader` and added `Shopware\Core\Framework\Store\Services\ExtensionLoader` as argument
* Changed `Shopware\Core\Framework\Store\Services\ExtensionDownloader::download` and throw `Shopware\Core\Framework\Store\Exception\StoreApiException` instead of `GuzzleHttp\Exception\ClientException`
___
# Administration
* Changed `Resources/app/administration/.gitignore` and added `test/_mocks_/entity-schema.json`
* Added data prop `installationFailedError` to `Resources/app/administration/src/module/sw-extension/component/sw-extension-card-bought/index.js` in order to store errors from failed installation attempts
* Changed method `downloadExtension` in `Resources/app/administration/src/module/sw-extension/service/extension-store-action.service.js` and pass context `Shopware.Context.api` to `basicHeaders` call
