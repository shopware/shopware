---
title: Update ESLint rules
issue: NEXT-38045
---
# Storefront
* Added ESLint rule `semi` to enforce semicolons after statements.
* Added ESLint rule `keyword-spacing` to enforce spaces before and after keywords such as `if ()` or `} else if (bar)`.
* Added folder `Resources/app/storefront/build` to ESLint.
* Added composer command `eslint:storefront:fix` to fix storefront ESLint only.