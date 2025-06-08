---
title: Refactor the code flow builder
issue: NEXT-25315
---
# Administration
* Changed in `module/sw-flow/component/sw-flow-sequence-action/index.js`:
  * Deprecated `appFlowActionRepository` computed.
  * Changed `groups` computed base new variable.
  * Changed computed modalName based new variable.
  * Deprecated `actionDescription` computed.
  * Changed content of `createdComponent` method to remove fetch multiple app actions
  * Deprecated method `getSelectedAppFlowAction`.
  * Deprecated `createdComponent` methods.
  * Changed `moveAction` method base new variable.
  * Deprecated `getAppFlowAction` methods.
  * Deprecated `convertTagString` methods.
  * Deprecated `getActionDescription` methods.
  * Deprecated `getSetOrderStateDescription` methods.
  * Deprecated `getGenerateDocumentDescription` methods.
  * Deprecated `getCustomerGroupDescription` methods.
  * Deprecated `getCustomerStatusDescription` methods.
  * Deprecated `getMailSendDescription` methods.
  * Deprecated `getCustomFieldDescription` methods.
  * Deprecated `getAffiliateAndCampaignCodeDescription` methods.
  * Deprecated `getAppFlowActionDescription` methods.
  * Deprecated `formatValuePreview` methods.
  * Deprecated `convertLabelPreview` methods.
* Changed in `module/sw-flow/component/sw-flow-sequence-action/sw-flow-sequence-action.html.twig` for new method to get descriptions.
* Added `appFlowActionRepository` computed in `/module/sw-flow/page/sw-flow-detail/index.js` to get App actions.
* Added `getAppFlowAction` method in `/module/sw-flow/page/sw-flow-detail/index.js` to get App actions.
* Added in `module/sw-flow/service/flow-builder.service.js` for reusing in the commercial plugin.
  - `getDescription`
  - `getActionDescriptions`
  - `getCustomerStatusDescription`
  - `getAffiliateAndCampaignCodeDescription`
  - `getCustomerGroupDescription`
  - `getCustomFieldDescription`
  - `getSetOrderStateDescription`
  - `convertTagString`
  - `getGenerateDocumentDescription`
  - `getMailSendDescription`
  - `convertConfig`
  - `getAppFlowActionDescription`
  - `formatValuePreview`
  - `convertLabelPreview`
  -  `addActionConstants`
  -  `addIcons`
  -  `addLabels`
  -  `getActionGroupMapping`
  -  `addActionGroupMapping`
  -  `getGroup`
  -  `getGroups`
  -  `addGroups`
* Added in `module/sw-flow/state/flow.state.js`:
  - `appActions` states.
  - `setAppActions` mutations.
  - `appActions`, `getSelectedAppAction` getters.
