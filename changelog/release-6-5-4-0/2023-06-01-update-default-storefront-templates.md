---
title: Update default storefront templates to improve developer experience
issue: NEXT-29324
author: Robert Fischer
author_email: info@sandoba.com
author_github: sandoba
---
# Storefront
* Changed several Twig templates in `Resources/views/storefront` to improve developer experience
    * Remove spelling errors
    * Standardize formatting for Twig filters
    * Remove or add spaces within Twig syntax where necessary
    * Remove or add empty lines where necessary
    * Remove unnecessary end tags (`/>`) for HTML tags such as `<meta>`, `<link>`, `<br>`, `<input>`
    * Fix indentation of code
    * Standardize order of properties in some HTML elements such as `<input>`
* Deprecated the following blocks in `Resources/views/storefront/page/checkout/finish/finish-details.html.twig` because they were not properly deprecated due to a spelling mistake:
    * Deprecated `page_checkout_finish_action`. Use upper block `page_checkout_finish_teaser` instead.
    * Deprecated `page_checkout_finish_action_back`. Use upper block `page_checkout_finish_teaser` instead.
