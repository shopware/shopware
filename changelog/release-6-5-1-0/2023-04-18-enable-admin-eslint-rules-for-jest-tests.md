---
title: Enable admin eslint rules for jest tests
issue: NEXT-26231
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Changed the `.eslintrc.js` for the administration for `.spec` files to also enable the following rules:
    * `jest/no-duplicate-hooks`
    * `jest/no-test-return-statement`
    * `jest/prefer-hooks-in-order`
    * `jest/prefer-hooks-on-top`
    * `jest/prefer-to-be`
    * `jest/require-top-level-describe`
    * `jest/prefer-to-contain`
    * `jest/prefer-to-have-length`
    * `jest/consistent-test-it`
* Changed 626 test files to comply with the rule changes