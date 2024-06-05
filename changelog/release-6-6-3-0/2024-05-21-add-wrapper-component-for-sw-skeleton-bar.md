---
title: Add wrapper component for sw-skeleton-bar
issue: NEXT-34291
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-skeleton-bar
* Added codemods (ESLint rules) for converting sw-card to mt-skeleton-bar
___
# Next Major Version Changes

## Removal of "sw-skeleton-bar":
The old "sw-skeleton-bar" component will be removed in the next major version. Please use the new "mt-skeleton-bar" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-skeleton-bar" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-skeleton-bar" with "mt-skeleton-bar".

Following changes are necessary:

### "sw-skeleton-bar" is removed
Replace all component names from "sw-skeleton-bar" with "mt-skeleton-bar"

Before:
```html
<sw-skeleton-bar>Hello World</sw-skeleton-bar>
```
After:
```html
<mt-skeleton-bar>Hello World</mt-skeleton-bar>
```
