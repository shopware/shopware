---
title: Refactor flow builder core
issue: NEXT-19345
---
# Core
* Added `DelayAware` interface at `Shopware\Core\Framework\Event` which defines if flow actions are able to delay.
* Added `DelayAware` as one of the return elements of the following requirements of action:
  * `AddCustomerAffiliateAndCampaignCodeAction.php`
  * `AddCustomerTagAction.php`
  * `AddOrderAffiliateAndCampaignCodeAction.php`
  * `AddOrderTagAction.php`
  * `ChangeCustomerGroupAction.php`
  * `ChangeCustomerStatusAction.php`
  * `GenerateDocumentAction.php`
  * `RemoveCustomerTagAction.php`
  * `RemoveOrderTagAction.php`
  * `SendMailAction.php`
  * `SetCustomerCustomFieldAction.php`
  * `SetCustomerGroupCustomFieldAction.php`
  * `SetOrderCustomFieldAction.php`
  * `SetOrderStateAction.php`
* Added `delayed` property to `src/Core/Content/Flow/Dispatching/FlowState.php` with default value is false.
* Added `currentSequence` nullable property to `src/Core/Content/Flow/Dispatching/FlowState.php`.
