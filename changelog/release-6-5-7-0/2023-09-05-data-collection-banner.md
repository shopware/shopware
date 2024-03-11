---
title: Add consent banner for usage data collection
issue: NEXT-29277
author: Lukas Boecker
author_email: l.boecker@shopware.com
author_github: lbocker
---
# Core
* Added a `missingUserInContextSource` exception to `Shopware\Core\System\UsageData\UsageDataException`
* Added static method `::invalidContextSource` to `Shopware\Core\System\UsageData\UsageDataException`
* Changed constant value `SYSTEM_CONFIG_KEY_SHARE_DATA` in `Shopware\Core\System\UsageData\Approval\ApprovalDetector`
* Added constant `USER_CONFIG_KEY_HIDE_CONSENT_BANNER` to `Shopware\Core\System\UsageData\Approval\ApprovalDetector`
* Removed method `::needsApprovalRequest()` in `Shopware\Core\System\UsageData\Approval\ApprovalDetector`
* Added method `::hasUserHiddenConsentBanner()` to `Shopware\Core\System\UsageData\Approval\ApprovalDetector`
___
# API
* Added route `GET /api/usage-data/consent` (`api.usage_data.get_consent`)
* Added route `POST /api/usage-data/consent` (`api.usage_data.update_consent`)
* Added route `POST /api/usage-data/hide-consent-banner` (`api.usage_data.hide_consent_banner`)
___
# Administration
* Added initializer `src/app/init-post/usage-data.init`
* Added login and logout notification listeners in `src/core/service/usage-data-consent-listener.service`
* Added Vuex store `src/app/state/usage-data.store`
* Added API service `src/core/service/api/usage-data.api.service`
* Added component `src/module/sw-settings-usage-data/component/sw-usage-data-consent-banner`
* Changed component `src/module/sw-dashboard/page/sw-dashboard-index` to display new component `src/module/sw-settings-usage-data/component/sw-usage-data-consent-banner`
* Changed component `src/module/sw-settings-usage-data/page/sw-settings-usage-data` to use new component `src/module/sw-settings-usage-data/component/sw-usage-data-consent-banner`
