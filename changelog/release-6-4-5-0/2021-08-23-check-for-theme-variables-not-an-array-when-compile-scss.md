---
title: Check for theme variables not an array when compile scss
issue: NEXT-8458
---
# Storefront
*  Changed method `\Shopware\Storefront\Theme\ThemeCompiler::dumpVariables` to not compile theme variables which contain array values.
