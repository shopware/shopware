---
title: Add wrapper component for sw-external-link
issue: NEXT-34292
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-external-link
* Added codemods (ESLint rules) for converting sw-card to mt-external-link
___
# Next Major Version Changes

## Removal of "sw-external-link":
The old "sw-external-link" component will be removed in the next major version. Please use the new "mt-external-link" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-external-link" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-external-link" with "mt-external-link".

Following changes are necessary:

### "sw-external-link" is removed
Replace all component names from "sw-external-link" with "mt-external-link"

Before:
```html
<sw-external-link>Hello World</sw-external-link>
```
After:
```html
<mt-external-link>Hello World</mt-external-link>
```

### "sw-external-link" property "icon" is removed
The "icon" property is removed from the "mt-external-link" component. There is no replacement for this property.

Before:
```html
<sw-external-link icon="world">Hello World</sw-external-link>
```
After:
```html
<mt-external-link>Hello World</mt-external-link>
```
