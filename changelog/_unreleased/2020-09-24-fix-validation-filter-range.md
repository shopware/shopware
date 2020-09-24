---
title:              Fix the filter range slider
issue:              NEXT-11068
author:             Sebastian König
author_email:       s.koenig@tinect.de
author_github:      @tinect
---
# Storefront
*  Added attribute `step="any"` to input fields in `component/listing/filter/filter-range.html.twig` to respect decimal values
*  Changed name of private method `_isInputInvalid` of `listing/filter-range.plugin.js` to `_isMinInvalid`
*  Changed name of private method `_setError` of `listing/filter-range.plugin.js` to `_setMinError`
*  Changed private method `_onChangeInput` of `listing/filter-range.plugin.js` to respect client side validation
*  Changed private method `_getErrorMessageTemplate` of `listing/filter-range.plugin.js` to have message as argument
*  Changed call of `this.listing.changeListing()` in private method `_onChangeInput` of `listing/filter-range.plugin.js` to be not called if any error occurred
*  Added private method `_isInvalid` to `listing/filter-range.plugin.js` to validate input fields on client side correctly
*  Added private method `_setDefaultError` to `listing/filter-range.plugin.js` to report validation errors to client
