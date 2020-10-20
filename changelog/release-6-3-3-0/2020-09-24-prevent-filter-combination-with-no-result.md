---
title: Prevent filter combinations with no result
issue: NEXT-5993
---
# Core
* Added possibility to differentiate between install and update process in a migration
___
# Storefront
* Added possibility for filter plugins to disable themselves when filter combinations are not available
* Added `widgets.search.filter` route to `SearchController`
* Added parameter `event` to private method `_onChangeRating` in the `filter-rating.plugin.js` plugin
___
# Upgrade Information
## Support of disabled filters feature 
If your custom filter should support the new feature you need to create a `refreshDisabledState()` function. This plugin gets two parameters:
- `filters`: Result of the `widgets.search.filter` request
- `filterParams`: Actual filter parameters of the page

These parameters can be used to refresh the state of the filter plugin.
Here is a small example for a boolean filter: 
```
refreshDisabledState(filter) {
        let value = 0;

        const booleanFilter = filter[this.options.name];

        if (booleanFilter.max) {
            value = booleanFilter.max;
        }

        if(value > 0) {
            this.el.classList.remove('disabled');
            this.checkbox.removeAttribute('disabled');
        } else {
            this.el.classList.add('disabled');
            this.checkbox.disabled = true;
        }
    }
```
