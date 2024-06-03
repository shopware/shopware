---
title: Add wrapper component for sw-textarea-field
issue: NEXT-34282
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-textarea-field
* Added codemods (ESLint rules) for converting sw-textarea-field to mt-textarea
___
# Next Major Version Changes

## Removal of "sw-textarea-field":
The old "sw-textarea-field" component will be removed in the next major version. Please use the new "mt-textarea" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-textarea" component. In this specific component it cannot convert anything correctly, because the new "mt-textarea" component has a different API. You have to manually check and solve every "TODO" comment created by the codemod.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-textarea-field" with "mt-textarea".

Following changes are necessary:

### "sw-textarea-field" is removed
Replace all component names from "sw-textarea-field" with "mt-textarea"

Before:
```html
<sw-textarea-field />
```
After:
```html
<mt-textarea />
```

### "sw-textarea-field" property "value" is replaced by "modelValue"
Replace all occurrences of the property "value" with "modelValue"

Before:
```html
<sw-textarea-field :value="myValue" />
```
After:
```html
<mt-textarea :modelValue="myValue" />
```

### "sw-textarea-field" binding "v-model:value" is replaced by "v-model"
Replace all occurrences of the binding "v-model:value" with "v-model"

Before:
```html
<sw-textarea-field v-model:value="myValue" />
```

After:
```html
<mt-textarea v-model="myValue" />
```

### "sw-textarea-field" slot "label" is replaced by property "label"
Replace all occurrences of the slot "label" with the property "label"

Before:
```html
<sw-textarea-field>
    <template #label>
        My Label
    </template>
</sw-textarea-field>
```

After:
```html
<mt-textarea label="My Label" />
```

### "sw-textarea-field" event "update:value" is replaced by "update:modelValue"
Replace all occurrences of the event "update:value" with "update:modelValue"

Before:
```html
<sw-textarea-field @update:value="onUpdateValue" />
```

After:
```html
<mt-textarea @update:modelValue="onUpdateValue" />
```

