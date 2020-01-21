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

    *
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
