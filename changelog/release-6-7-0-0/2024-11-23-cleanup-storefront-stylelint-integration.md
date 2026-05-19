---
title: Cleanup storefront stylelint integration
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed `stylelint` version to 16.16.0
* Removed not needed `stylelint-junit-formatter` dependency
* Removed not needed `shopware/object-fit-polyfill` `stylelint` rule
* Removed not needed `postcss-html` dependency
* Added `stylelint-prettier`
* Changed `stylelint` base config from `stylelint-config-sass-guidelines` to `stylelint-config-recommended-scss`
* Changed SCSS files to adhere to new `stylelint` rules and updates
