---
title: Exchange sw-progress-bar
issue: NEXT-34297
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Added wrapper component for sw-progress-bar
* Added codemods (ESLint rules) for converting sw-progress-bar to mt-progress-bar
___
# Next Major Version Changes

## Removal of "sw-progress-bar":
The old "sw-progress-bar" component will be removed in the next major version. Please use the new "mt-progress-bar" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-progress-bar" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-progress-bar" with "mt-progress-bar".

Following changes are necessary:

### "sw-progress-bar" is removed
Replace all component names from "sw-progress-bar" with "mt-progress-bar"

Before:
```html
<sw-progress-bar />
```
After:
```html
<mt-progress-bar />
```

### "mt-progress-bar" has no property "value" anymore
Replace all occurrences of the "value" prop with "modelValue"

Before:
```html
<mt-progress-bar value="5" />
```
After:
```html
<mt-progress-bar modelValue="5" />
```

### "mt-progress-bar" v-model:value is deprecated
Replace all occurrences of the "v-model:value" directive with "v-model"

Before:
```html
<mt-progress-bar v-model:value="myValue" />
```
After:
```html
<mt-progress-bar v-model="myValue" />
```

### "mt-progress-bar" has no event "update:value" anymore
Replace all occurrences of the "update:value" event with "update:modelValue"

Before:
```html
<mt-progress-bar @update:value="updateValue" />
```

After:
```html
<mt-progress-bar @update:modelValue="updateValue" />
```g
