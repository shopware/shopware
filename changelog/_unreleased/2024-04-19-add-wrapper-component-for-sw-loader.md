---
title: Add wrapper component for sw-loader
issue: NEXT-34276
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-loader
* Added codemods (ESLint rules) for converting sw-loader to mt-loader
___
# Next Major Version Changes

## Removal of "sw-loader":
The old "sw-loader" component will be removed in the next major version. Please use the new "mt-loader" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-loader" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-loader" with "mt-loader".

Following changes are necessary:

### "sw-loader" is removed
Replace all component names from "sw-loader" with "mt-loader"

Before:
```html
<sw-loader />
```
After:
```html
<mt-loader />
```
