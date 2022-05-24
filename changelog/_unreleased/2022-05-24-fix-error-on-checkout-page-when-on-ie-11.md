---
title: Fix error on checkout page when on IE 11
issue: NEXT-20726
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com
author_github: @lernhart
---
# Storefront
* Changed `URLSearchParams` to `query-string` package inside `Resources/app/storefront/src/plugin/forms/form-auto-submit.plugin.js` in order to ensure IE11 compatibility
