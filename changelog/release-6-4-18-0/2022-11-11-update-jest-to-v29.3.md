---
title: Update Jest to V29.3
issue: NEXT-24123
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Changed jest version to `29.3` from `27.5.1`
* Added `ts-jest`, `jest-jasmine2`, `jest-environment-jsdom` and `babel-jest` as separate dependencies as they don't come bundled with jest anymore.
* Changed various tests to make them work with the new jest version
