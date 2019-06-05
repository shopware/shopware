[titleEn]: <>(Refactored fillSwSelectComponent command)

A first step to unify all `sw-select` behaviour was merged today.
We refactored the E2E custom command `fillSwSelectComponent` to support `sw-select` in both variants
(single and multi select) as well as `sw-single-select`, `sw-multi-select` and `sw-tag-field`.

We also renamed it to `fillSwSelect` instead of `fillSwSelectCommand`

```
fillSwSelect(
    selector,
    {
      value,
      clearField = false,
      isMulti = false,
      searchTerm = null,
      resultPosition = 0
    }
) 
```

* `selector` The css selector of your select component.
* `value` The value to search and check against after selecting it.
* `clearField` Indicates if all selections should be cleared before selecting a new value. (multi select only)
* `isMulti` Indicates if the select field is a multi select.
* `searchTerm` Overrides the value to search for, if it differs from the actual value. (e.g. search for a locale but the displayed value is the locale description)
* `resultPosition` Tells nightwatch to select a specific option from the results list rather than the first. This might be necessary if your search has more than one result.

To avoid duplications we removed the E2E commands `fillSwSingleSelect` and `fillSwMultiSelect`.   