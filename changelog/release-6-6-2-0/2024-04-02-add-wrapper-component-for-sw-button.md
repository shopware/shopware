---
title: Add wrapper component for sw-button
issue: NEXT-34269
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-button
* Added codemods (ESLint rules) for converting sw-button to mt-button
___
# Next Major Version Changes
## Removal of "sw-button":
The old "sw-button" component will be removed in the next major version. Please use the new "mt-button" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-button" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-button" with "mt-button".

Following changes are necessary:

### "sw-button" is removed
Replace all component names from "sw-button" with "mt-button"

Before:
```html
<sw-button>Save</sw-button>
```
After:
```html
<mt-button>Save</mt-button>
```

### "mt-button" has no value "ghost" in property "variant" anymore
Remove the property "variant". Use the property "ghost" instead.

Before:
```html
<sw-button variant="ghost">Save</sw-button>
```
After:
```html
<mt-button ghost>Save</mt-button>
```

### "mt-button" has no value "danger" in property "variant" anymore
Replace the value "danger" with "critical" in the property "variant".

Before:
```html
<sw-button variant="danger">Delete</sw-button>
```
After:
```html
<mt-button variant="critical">Delete</mt-button>
```

### "mt-button" has no value "ghost-danger" in property "variant" anymore
Replace the value "ghost-danger" with "critical" in the property "variant". Add the property "ghost".

Before:
```html
<sw-button variant="ghost-danger">Delete</sw-button>
```
After:
```html
<mt-button variant="critical" ghost>Delete</mt-button>
```

### "mt-button" has no value "contrast" in property "variant" anymore
Remove the value "contrast" from the property "variant". There is no replacement.

### "mt-button" has no value "context" in property "variant" anymore
Remove the value "context" from the property "variant". There is no replacement.

### "mt-button" has no property "router-link" anymore
Replace the property "router-link" with a "@click" event listener and a "this.$router.push()" method.

Before:
```html
<sw-button router-link="sw.example.route">Go to example</sw-button>
```
After:
```html
<mt-button @click="this.$router.push('sw.example.route')">Go to example</mt-button>
```
