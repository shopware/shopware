---
title: AAdd wrapper component for sw-colorpicker
issue: NEXT-34295
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-colorpicker
* Added codemods (ESLint rules) for converting sw-colorpicker to mt-colorpicker
___
# Next Major Version Changes

## Removal of "sw-colorpicker":
The old "sw-colorpicker" component will be removed in the next major version. Please use the new "mt-colorpicker" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-colorpicker" component. In this specific component it cannot convert anything correctly, because the new "mt-colorpicker" component has a different API. You have to manually check and solve every "TODO" comment created by the codemod.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-colorpicker" with "mt-colorpicker".

Following changes are necessary:

### "sw-colorpicker" is removed
Replace all component names from "sw-colorpicker" with "mt-colorpicker"

Before:
```html
<sw-colorpicker />
```
After:
```html
<mt-colorpicker />
```

### "sw-colorpicker" property "value" is replaced by "modelValue"
Replace all occurrences of the property "value" with "modelValue"

Before:
```html
<sw-colorpicker :value="myValue" />
```
After:
```html
<mt-colorpicker :modelValue="myValue" />
```

### "sw-colorpicker" binding "v-model:value" is replaced by "v-model"
Replace all occurrences of the binding "v-model:value" with "v-model"

Before:
```html
<sw-colorpicker v-model:value="myValue" />
```

After:
```html
<mt-colorpicker v-model="myValue" />
```

### "sw-colorpicker" slot "label" is replaced by property "label"
Replace all occurrences of the slot "label" with the property "label"

Before:
```html
<sw-colorpicker>
    <template #label>
        My Label
    </template>
</sw-colorpicker>
```

After:
```html
<mt-colorpicker label="My Label" />
```

### "sw-colorpicker" event "update:value" is replaced by "update:modelValue"
Replace all occurrences of the event "update:value" with "update:modelValue"

Before:
```html
<sw-colorpicker @update:value="onUpdateValue" />
```

After:
```html
<mt-colorpicker @update:modelValue="onUpdateValue" />
```
