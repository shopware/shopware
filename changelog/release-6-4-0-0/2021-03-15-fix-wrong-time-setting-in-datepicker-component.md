---
title: Fix wrong time setting in datepicker component
issue: NEXT-11082
---
# Administration
* Changed method `createConfig` in `src/app/component/form/sw-datepicker/index.js` to use ISO Date format when using `datetime-local` type
* Changed `dateType` of release date field in `src/module/sw-product/component/sw-product-settings-form/sw-product-settings-form.html.twig` in order to use date time from user's browser
___
# Upgrade Information
## Datepicker component
According to document of flatpickr (https://flatpickr.js.org/formatting/), ISO Date format is now supported for datepicker component
With `datetime-local` date type, the datepicker will display to user their browser time and convert to UTC value
### Before
* Both dateType `datetime` and `datetime-local` use UTC timezone `(GMT+00:00)`
* If user select date `2021-03-22` and time `12:30`, the output is `2021-03-22T12:30:000+00:00`
### After
* With dateType `datetime`, user select date `2021-03-22` and time `12:30`, the output is `2021-03-22T12:30:000+00:00`
* With dateType `datetime-local`, user select date `2021-03-22` and time `12:30` and timezone of user is `GMT+07:00`, the output is `2021-03-22T05:30.00.000Z`
