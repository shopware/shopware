---
title: Add administration module to control event actions
issue: NEXT-10498
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Administration
* Added new module `sw-event-action` in `Resources/app/administration/src/module/sw-event-action`
    * Added module manifest `sw-event-action/index.js`
    * Added ACL privileges `sw-event-action/acl/index.js`
    * Added `sw-event-action/page/sw-event-action-detail/sw-event-action-detail`
    * Added `sw-event-action/page/sw-event-action-list/sw-event-action-list`
    * Added `sw-event-action/component/sw-event-action-detail-recipients`
    * Added `sw-event-action/component/sw-event-action-list-expand-labels`
    * Added new translation file `sw-event-action/snippet/de-DE.json`
    * Added new translation file `sw-event-action/snippet/en-GB.json`
    * Added `sw-event-action/helper/snake-case-event-name.helper.js`
* Added new API service to fetch all business events `Resources/app/administration/src/core/service/api/business-events.api.service.js`
* Added new SCSS file `sw-data-grid-column-boolean.scss` to component `Resources/app/administration/src/app/component/data-grid/sw-data-grid-column-boolean` in order to provide default colors for `true` and `false` default icons.
* Added root CSS class `.sw-data-grid-column-boolean` to `Resources/app/administration/src/app/component/data-grid/sw-data-grid-column-boolean/sw-data-grid-column-boolean.html.twig` template
* Added new date prop `testMailSalesChannelId` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`
* Deprecated multi select `sw_mail_template_basic_form_sales_channels_field` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/sw-mail-template-detail.html.twig`, will be removed
* Deprecated data prop `mailTemplateSalesChannels` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated data prop `mailTemplateSalesChannelsAssoc` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated data prop `salesChannelTypeCriteria` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated computed `mailTemplateSalesChannelAssociationRepository` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated association `salesChannels.salesChannel` from criteria of method `loadEntityData` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated method `createSalesChannelCollection` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated method `getPossibleSalesChannels` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated method `setSalesChannelCriteria` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated method `enrichAssocStores` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated method `handleSalesChannel` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated method `mailTemplateHasSaleschannel` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated method `salesChannelIsSelected` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated method `undeleteSaleschannel` in `Resources/app/administration/src/module/sw-mail-template/page/sw-mail-template-detail/index.js`, will be removed
* Deprecated component `Resources/app/administration/src/module/sw-order/component/sw-order-state-change-modal/sw-order-state-change-modal-assign-mail-template`, will be removed
* Deprecated computed `showDocuments` in `Resources/app/administration/src/module/sw-order/component/sw-order-state-change-modal`, will be removed
* Deprecated method `onNoMailConfirm` in `Resources/app/administration/src/module/sw-order/component/sw-order-state-change-modal`, will be removed
* Deprecated method `onAssignMailTemplate` in `Resources/app/administration/src/module/sw-order/component/sw-order-state-change-modal`, will be removed
* Deprecated `sw_order_state_change_modal_assign_mail_template_component` in `Resources/app/administration/src/module/sw-order/component/sw-order-state-change-modal/sw-order-state-change-modal.html.twig`, will be removed
* Deprecated data prop `mailTemplatesExist` in `Resources/app/administration/src/module/sw-order/component/sw-order-state-history-card/index.js`, will be removed
* Changed the `mailTemplatesExist` prop assignment value of `sw-order-state-change-modal` to `true` in `Resources/app/administration/src/module/sw-order/component/sw-order-state-history-card.html.twig`
___
# Core
* Added new flag `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking` to field `event_name` in `Shopware\Core\Framework\Event\EventAction\EventActionDefinition::defineFields`
* Added new flag `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking` to field `action_name` in `Shopware\Core\Framework\Event\EventAction\EventActionDefinition::defineFields`
---
# Upgrade Information

## New handling to assign mail templates to business events

With the new event action module (`sw-event-action`) the user can configure which mail template will be sent for a business event. This makes other assignments superfluous:
* The assignment for mail templates inside the order module (when changing the order state) is no longer needed.
* The component `sw-order-state-change-modal-assign-mail-template` is deprecated for `tag:v6.0.0` and is not being rendered anymore from now on. Changes which have been made to this component will not be visible.
* The assignment of sales channels inside the mail template detail page is no longer needed.
* The select field inside the block `sw_order_state_change_modal_assign_mail_template_component` in `Resources/app/administration/src/module/sw-order/component/sw-order-state-change-modal/sw-order-state-change-modal.html.twig` was removed.
  * The twig block is still present but css or template extensions which rely on the field to be displayed may have to be adjusted.
  * A sales channel selection is only needed in order to send a test mail and has been added to the `sidebar` slot of `sw-mail-template-detail` component.
