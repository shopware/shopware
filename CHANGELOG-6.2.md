CHANGELOG for 6.2.x
===================

This changelog references the relevant changes (bug and security fixes) done
in 6.2 minor versions.

To get the diff for a specific change, go to https://github.com/shopware/platform/commit/XXX where XXX is the change hash
To get the diff between two versions, go to https://github.com/shopware/platform/compare/v6.2.0-rc4...v6.2.0

### 6.2.0

**Addition / Changes**

* Administration
	* Added `disabled` attribute of fields to `sw-customer-address-form` component
    * Deprecated `tagStore` in `sw-newsletter-recipient-list`
    * Moved `sw-manufacturer` to the new data handling
        * Deprecated `mediaStore`
        * Added `mediaRepository`
        * Deprecated `customFieldSetStore`
        * Added `customFieldSetRepository`
        * Deprecated import of `StateDeprecated`
        * Rewritten `loadEntityData` so it uses the new data handling
        * Added `customFieldSetCriteria` as an computed property
    * Added `disabled` attribute of fields to `sw-customer-address-form` component
    * Refactored sw-radio-field
        * Deprecated currentValue, use value instead
        * Deprecated watcher for value

    *
    * The component sw-integration-list was refactored to use the "repositoryFactory" instead of "StateDeprecated" to fetch and save data
	    - deprecated "StateDeprecated"
	    - change default data "integrations" from "[]" to "null"
	    - deprecated computed "id"
	    - deprecated computed "integrationStore"
	    - deprecated block "sw_integration_list_grid_inner"
	    - deprecated block "sw_integration_list_grid_inner_slot_columns"
	    - deprecated block "sw_integration_list_grid_pagination"
    * Deprecated the use of `fixed-top` class in `header-minimal.html.twig`
    * The component sw-plugin-box was refactored to use the "repositoryFactory" instead of "StateDeprecated" to fetch and save data
            - removed "StateDeprecated"
            - removed computed "pluginStore"
    * The component sw-settings-payment-detail was refactored to use the "repositoryFactory" instead of "StateDeprecated" to fetch and save data
        - removed "StateDeprecated"
        - removed computed "paymentMethodStore"
        - removed computed "ruleStore"
        - removed computed "mediaStore"

* Core    
    *

* Storefront	
    *

**Removals**

* Administration
    *

* Core
    *    

* Storefront
    *	
* Core
    * Added hreflang support
