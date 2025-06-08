---
title: Add wrapper component for sw-icon
issue: NEXT-34270
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-icon
* Added codemods (ESLint rules) for converting sw-icon to mt-icon
___
# Next Major Version Changes
## Removal of "sw-icon":
The old "sw-icon" component will be removed in the next major version. Please use the new "mt-icon" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-icon" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-icon" with "mt-icon".

Following changes are necessary:

### "sw-icon" is removed
Replace all component names from "sw-icon" with "mt-icon"

Before:
```html
<sw-icon name="regular-times-s" />
```
After:
```html
<mt-icon name="regular-times-s" />
```

### "mt-icon" has no property "small" anymore
Replace the property "small" with "size" of value "16px" if used

Before:
```html
<sw-icon name="regular-times-s" small />
```
After:
```html
<mt-icon name="regular-times-s" size="16px" />
```

### "mt-icon" has no property "large" anymore
Replace the property "large" with "size" of value "32px" if used

Before:
```html
<sw-icon name="regular-times-s" large />
```

After:
```html
<mt-icon name="regular-times-s" size="32px" />
```

### "mt-icon" has different default sizes than "sw-icon"
If no property "size", "small" or "large" is used, you need to use the "size" prop with the value "24px" to avoid a different default size than with "sw-icon"

Before:
```html
<sw-icon name="regular-times-s" />
```
After:
```html
<mt-icon name="regular-times-s" size="24px" />
```
