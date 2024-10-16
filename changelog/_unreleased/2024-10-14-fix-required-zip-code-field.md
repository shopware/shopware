---
title: Fix required zip-code field
issue: NEXT-38751
author: Vladimir Miljkovic
author_email: vlada972010@gmail.com
author_github: @miljkovic5
---
# Storefront
* Changed selection of zip-code labels and inputs in `form-country-state-select.plugin.js` to `querySelectorAll` to match all possible fields.
* Changed `_updateZipcodeRequired` in `form-country-state-select.plugin.js` to update the required state of all selected fields accordingly.
