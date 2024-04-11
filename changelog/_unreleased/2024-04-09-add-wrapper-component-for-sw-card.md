---
title: Add wrapper component for sw-card
issue: NEXT-34272
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-card
* Added codemods (ESLint rules) for converting sw-card to mt-card
___
# Next Major Version Changes
## Removal of "sw-card":
The old "sw-card" component will be removed in the next major version. Please use the new "mt-card" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-card" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-card" with "mt-card".

Following changes are necessary:

### "sw-card" is removed
Replace all component names from "sw-card" with "mt-card"

Before:
```html
<sw-card>Hello World</sw-card>
```
After:
```html
<mt-card>Hello World</mt-card>
```

### "mt-card" has no property "aiBadge" anymore
Replace the property "aiBadge" by using the "sw-ai-copilot-badge" component directly inside the "title" slot

Before:
```html
<mt-card aiBadge>Hello Wolrd</mt-card>
```

After:
```html
<mt-card>
    <slot name="title"><sw-ai-copilot-badge /></slot>
    Hello World
</mt-card>
```

### "mt-card" has no property "contentPadding" anymore
The property "contentPadding" is removed without a replacement.

Before:
```html
<mt-card contentPadding>Hello World</mt-card>
```

After:
```html
<mt-card>Hello World</mt-card>
```
