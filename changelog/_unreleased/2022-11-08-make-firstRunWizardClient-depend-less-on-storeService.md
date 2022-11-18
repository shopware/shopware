---
title: make the FirstRunWizardClient depend less on StoreService
issue: NEXT-18846
author: Adrian Les
author_email: a.les@shopware.com
author_github: adrianles
---
# Core
* Added `Shopware\Core\Framework\Store\Services\TrackingEventClient`
* Changed `Shopware\Core\Framework\Store\Services\FirstRunWizardClient` to use `Shopware\Core\Framework\Store\Services\TrackingEventClient`
* Deprecated `Shopware\Core\Framework\Store\Services\StoreService::fireTrackingEvent()`
* Deprecated `Shopware\Core\Framework\Store\Services\StoreService::getLanguageByContext()`
