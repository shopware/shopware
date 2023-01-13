---
title: Form preserver iterates through wrong elements
issue: https://github.com/shopware/platform/issues/2227
author: Wanne Van Camp
author_email: wanne.vancamp@meteor.be
author_github: @wannevancamp
---
# Storefront
* Changed formelement selector `this.el.children` to `this.el.elements` in `Resources/app/storefront/src/plugin/forms/form-preserver.plugin.js` to select the correct input fields.