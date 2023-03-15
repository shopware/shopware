---
title: Fix customer cannot create with language not available
issue: NEXT-23955
---
# Administration
* Added `languageId` computed property in
  * `sw-customer-create` component.
  * `sw-order-new-customer-modal` component
* Added `languageRepository` computed property in
  * `sw-customer-create` component.
  * `sw-order-new-customer-modal` component.
* Added `languageCriteria` computed property in
  * `sw-customer-create` component to filter languages by `salesChannelId`.
  * `sw-order-new-customer-modal` component to filter languages by `salesChannelId`.
* Added `loadLanguage` method in
  * `sw-customer-create` component to get the `language`.
  * `sw-order-new-customer-modal` component to get the `language`.
