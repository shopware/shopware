---
title: Add wrapper component for sw-datepicker
issue: NEXT-34282
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-datepicker
* Added codemods (ESLint rules) for converting sw-datepicker to mt-datepicker
___
# Next Major Version Changes

## Removal of "sw-datepicker":
The old "sw-datepicker" component will be removed in the next major version. Please use the new "mt-datepicker" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-datepicker" component. In this specific component it cannot convert anything correctly, because the new "mt-datepicker" component has a different API. You have to manually check and solve every "TODO" comment created by the codemod.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-datepicker" with "mt-datepicker".

Following changes are necessary:

### "sw-datepicker" is removed
Replace all component names from "sw-datepicker" with "mt-datepicker"

Before:
```html
<sw-datepicker />
```
After:
```html
<mt-datepicker />
```

### "sw-datepicker" property "value" is replaced by "modelValue"
Replace all occurrences of the property "value" with "modelValue"

Before:
```html
<sw-datepicker :value="myValue" />
```
After:
```html
<mt-datepicker :modelValue="myValue" />
```

### "sw-datepicker" binding "v-model:value" is replaced by "v-model"
Replace all occurrences of the binding "v-model:value" with "v-model"

Before:
```html
<sw-datepicker v-model:value="myValue" />
```

After:
```html
<mt-datepicker v-model="myValue" />
```

### "sw-datepicker" slot "label" is replaced by property "label"
Replace all occurrences of the slot "label" with the property "label"

Before:
```html
<sw-datepicker>
    <template #label>
        My Label
    </template>
</sw-datepicker>
```

After:
```html
<mt-datepicker label="My Label" />
```

### "sw-datepicker" event "update:value" is replaced by "update:modelValue"
Replace all occurrences of the event "update:value" with "update:modelValue"

Before:
```html
<sw-datepicker @update:value="onUpdateValue" />
```

After:
```html
<mt-datepicker @update:modelValue="onUpdateValue" />
```
