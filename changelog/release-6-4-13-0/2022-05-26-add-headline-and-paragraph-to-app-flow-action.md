---
title: Add headline and paragraph to App Flow Action
issue: NEXT-21323
---
# Core
* Added the `$headline` protected in:
  `Shopware\Core\Framework\App\Aggregate\FlowActionTranslation\AppFlowActionTranslationEntity`,
  `Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity`
* Changed the `defineFields` method to add more translation `headline` field in: 
  `Shopware\Core\Framework\App\Aggregate\FlowActionTranslation\AppFlowActionTranslationDefinition`,
  `Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition`,
* Added new element `<xs:element type="translatableString" name="headline" maxOccurs="unbounded" minOccurs="0"/>` in `src/Core/Framework/App/FlowAction/Schema/flow-action-1.0.xsd` to support define `<headline>` tag in App Flow Action.
* Added new `$headline` protected in `Shopware\Core\Framework\App\FlowAction\Xml\Metadata`.
* Deleted `$badge` protected in `Shopware\Core\Framework\App\FlowAction\Xml\Metadata`.
* Added new `Migration1653385302AddHeadlineColumnToAppFlowActionTable` migration to add the `headline` column to `app_flow_action_translation` table.
* Changed `toArray` method in `Shopware\Core\Framework\App\FlowAction\Xml\Action` to get the `headline` element in App Flow Action.
___
# Administration
* Added new `sw_flow_app_action_modal_headline`, `sw_flow_app_action_modal_paragraph` and `sw_flow_app_action_modal_headline` blocks in `src/module/sw-flow/component/modals/sw-flow-app-action-modal/sw-flow-app-action-modal.html.twig` to show the action headline and paragraph.
* Added new `headline` and `paragraph` methods in `src/module/sw-flow/component/modals/sw-flow-app-action-modal/index.js` to get `headline` and `paragraph`.
