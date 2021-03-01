---
title: Implement ACL for Custom search and index ranking module
issue: NEXT-13437
---
# Administration
* Changed `src/module/sw-settings-search/acl/index.js` to update privileges of role viewer, editor, creator and deleter.
* Changed to inject the `ACL` services to these components:
    * `sw-settings-search-excluded-search-terms`
    * `sw-settings-search-excluded-search-terms`
    * `sw-settings-search-search-index`
    * `sw-settings-search-searchable-content`
    * `sw-settings-search-searchable-content-customfields`
    * `sw-settings-search-searchable-content-general`
* Changed `src/module/sw-settings-search/component/sw-settings-search-search-behaviour/sw-settings-search-search-behaviour.html.twig` to add `v-tooltip` and `disabled` attributes on block `sw_settings_search_search_behaviour_condition` and block `sw_settings_search_search_behaviour_search_term_length`.
* Changed `src/module/sw-settings-search/component/sw-settings-search-searchable-content-customfields/sw-settings-search-searchable-content-customfields.html.twig` to update block `sw_settings_search_searchable_content_customfields_columns_actions_edit` change link element <a> to <sw-context-menu-item>.
* Changed `src/module/sw-settings-search/component/sw-settings-search-searchable-content-customfields/sw-settings-search-searchable-content-customfields.html.twig` to update block `sw_settings_search_searchable_content_customfields_columns_actions_delete` and change link element <a> to <sw-context-menu-item>.
