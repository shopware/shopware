---
title: Implement mt-banner code mod
issue: NEXT-34278
flag: V6_7_0_0
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Added wrapper component for sw-alert
* Added codemods (ESLint rules) for converting sw-alert to mt-banner
___
# Next Major Version Changes
## Removal of "sw-alert" & "sw-alert-deprecated":
The old "sw-alert" component will be removed in the next major version. Please use the new "mt-banner" component instead.

We provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-banner" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-alert" with "mt-banner".

Following changes are necessary:

### "sw-alert" is removed
Replace all component names from "sw-alert" with "mt-banner"

Before:
```html
<sw-alert />
```
After:
```html
<mt-banner />
```

### Variants warning, critical and success must be replaced
Before:
```html
<sw-alert variant="success" />
<sw-alert variant="warning" />
<sw-alert variant="error" />
```

After:
```html
<sw-alert variant="positive" />
<sw-alert variant="attention" />
<sw-alert variant="critical" />
```

### Property appearance was removed
Before:
```html
<sw-alert appearence="..." />
```

After:
- Custom styling will be necessary

### Property showIcon got replaced by hideIcon
Before:
```html
<sw-alert :show-icon="condition" />
```

After:
```html
<sw-alert :hide-icon="!condition" />
```

### Slot actions got removed
Before:
```html
<sw-alert>
    <template #actions>
        ...
    </template>
</sw-alert>
```

After:
- Incorporate your actions elsewhere
