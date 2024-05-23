---
title: Add wrapper component for sw-url-field
issue: NEXT-34296
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-url-field
* Added codemods (ESLint rules) for converting sw-card to mt-url-field
___
# Next Major Version Changes

## Removal of "sw-url-field":
The old "sw-url-field" component will be removed in the next major version. Please use the new "mt-url-field" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-url-field" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-url-field" with "mt-url-field".

Following changes are necessary:

### "sw-url-field" is removed
Replace all component names from "sw-url-field" with "mt-url-field"

Before:
```html
<sw-url-field />
```
After:
```html
<mt-url-field />
```

### "mt-url-field" has no property "value" anymore
Replace all occurrences of the "value" prop with "modelValue"

Before:
```html
<sw-url-field value="Hello World" />
```
After:
```html
<mt-url-field modelValue="Hello World" />
```

### "mt-url-field" v-model:value is deprecated
Replace all occurrences of the "v-model:value" directive with "v-model"

Before:
```html
<sw-url-field v-model:value="myValue" />
```
After:
```html
<mt-url-field v-model="myValue" />
```

### "mt-url-field" has no event "update:value" anymore
Replace all occurrences of the "update:value" event with "update:modelValue"

Before:
```html
<sw-url-field @update:value="updateValue" />
```

After:
```html
<mt-url-field @update:modelValue="updateValue" />
```

### "mt-url-field" has no slot "label" anymore
Remove all occurrences of the "label" slot. The slot content should be moved to the "label" prop. Only string values are supported. Other slot content is not supported
anymore.

Before:
```html
<sw-url-field>
    <template #label>
        My Label
    </template>
</sw-url-field>
```
After:
```html
<mt-url-field label="My label">
</mt-url-field>
```

### "mt-url-field" has no slot "hint" anymore
Remove all occurrences of the "hint" slot. There is no replacement for this slot.

Before:
```html
<sw-url-field>
    <template #hint>
        My Hint
    </template>
</sw-url-field>
```

After:
```html
<mt-url-field />
```
