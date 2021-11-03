---
title: Add deprecated info for business event module
issue: NEXT-16412
---
# Administration
* Added component `sw-event-action-deprecated-modal` to show first time only on Business Event listing page to let user know about Flow builder module. 
* Added component `sw-event-action-deprecated-alert` to show on Business Event listing page and detail page for user to know that this module will be replaced with newly Flow builder module.
* Changed `module/sw-event-action/page/sw-event-action-list/sw-event-action-list.html.twig` to add two blocks `sw_event_action_list_deprecated_modal` and `sw_event_action_list_deprecated_alert` for showing the deprecated modal, alert.
* Changed `module/sw-event-action/page/sw-event-action-list/sw-event-action-detail.html.twig` to add two block `sw_event_action_list_deprecated_alert` for showing the deprecated alert.
* Deprecated module `sw-event-action` - Business Event  in favor for `sw-flow` - Flow Builder.
    * The following components got deprecated:
        * `sw-event-action-deprecated-alert`
        * `sw-event-action-deprecated-modal`
        * `sw-event-action-detail-recipients`
        * `sw-event-action-list-expand-labels`
        * `sw-event-action-detail`
        * `sw-event-action-list`
    * The following services and helpers got deprecated:
        * `sw-event-action/acl/index.js`
        * `src/core/service/api/business-events.api.service.js`
