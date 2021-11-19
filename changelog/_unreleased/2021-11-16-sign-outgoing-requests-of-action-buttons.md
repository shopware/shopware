---
title: Sign outgoing requests of action buttons
issue: NEXT-18240
author: Frederik Schmitt
author_email: f.schmitt@shopware.com 
author_github: fschmtt
---
# Core
* Changed `Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponse` to hold only the property `$actionType`
* Changed `Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory` to have the `ActionButtonResponse` created by distinct factories
* Added `Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactoryInterface`
* Changed `Shopware\Core\Framework\App\ActionButton\Response\NotificationResponse` to only hold properties
* Added `Shopware\Core\Framework\App\ActionButton\Response\NotificationResponseFactory`
* Changed `Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponse` to only hold properties
* Added `Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponseFactory`
* Changed `Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponse` to only hold properties
* Added `Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponseFactory`
* Changed `Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponse` to only hold properties
* Added `Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponseFactory`
* Changed `Shopware\Core\Framework\App\ActionButton\Executor::execute()` to use new factories
* Added `Shopware\Core\Framework\App\Hmac\QuerySigner` which adds and signs query parameters
* Added `Shopware\Core\Framework\App\Hmac\RequestSigner::signUri()` to allow signing of query parameters
___
# Administration
* Changed `actionTypeConstants.ACTION_SHOW_NOTITFICATION` to `actionTypeConstants.ACTION_SHOW_NOTIFICATION` in `src/app/component/app/sw-app-actions/index.js`
* Changed `src/app/component/app/sw-app-actions/sw-app-actions.html.twig` to move the confirmation modal out of the iframe modal
* Changed `src/app/component/app/sw-app-actions/sw-app-actions.scss` to vertically align the app icon in the iframe modal header
___
# Upgrade Information
App manufacturers who add action buttons which provide feedback to the Administration are now able to access the following meta-information:

| Query parameter | Example value | Description |
|---|---|---|
| shop-id | KvhpuoEVXWmtjkQa | The ID of the shop where the action button was triggered. |
| shop-url | https://shopware.com | The URL of the shop where the action button was triggered. |
| sw-version | 6.4.7.0 | The installed Shopware version of the shop where the action button was triggered. |
| sw-context-language | 2fbb5fe2e29a4d70aa5854ce7ce3e20b | The language (UUID) of the context (`Context::getLanguageId()`). |
| sw-user-language | en-GB | The language (ISO code) of the user who triggered the action button. |
| shopware-shop-signature | `hash_hmac('sha256', $query, $shopSecret)` | The hash of the the query, signed with the shop's secret. |

You **must** make sure to verify the authenticity of the incoming request by checking the `shopware-shop-signature`!
