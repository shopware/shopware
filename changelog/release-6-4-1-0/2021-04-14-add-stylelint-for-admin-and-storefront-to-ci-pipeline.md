---
title: Add stylelint for admin and storefront to CI pipeline
issue: NEXT-14702
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Added pipeline tests for stylelint
* Changed behavior of npm commands `lint:scss`
* Changed npm command `lint:scss:fix`to `lint:scss-fix`
* Removed npm command `lint:scss-all:fix`
___
# Storefront
* Added pipeline tests for stylelint and ESLint
* Added npm command `lint:js`, `lint:scss` and `lint:scss-fix`
* Removed eslint from npm command `unit` 
* Removed stylelint from webpack config
