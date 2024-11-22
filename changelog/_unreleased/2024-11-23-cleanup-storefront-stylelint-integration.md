---
title: Cleanup storefront stylelint integration
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed `stylelint` version to 16.10.0
* Changed `stylelint-junit-formatter` to a dev dependencies
* Removed not needed `shopware/object-fit-polyfill` `stylelint` rule
* Removed not needed `postcss-html` dependency
* Added `stylelint-prettier`
* Changed `stylelint` base config from `stylelint-config-sass-guidelines` to `stylelint-config-recommended-scss`
* Changed SCSS files to adhere to new `stylelint` rules and updates
