---
title: Add wrapper component for sw-password-field
issue: NEXT-34290
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-password-field
* Added codemods (ESLint rules) for converting sw-card to mt-password-field
___
# Next Major Version Changes

## Removal of "sw-password-field":
The old "sw-password-field" component will be removed in the next major version. Please use the new "mt-password-field" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-password-field" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-password-field" with "mt-password-field".

Following changes are necessary:

### "sw-password-field" is removed
Replace all component names from "sw-password-field" with "mt-password-field"

Before:
```html
<sw-password-field>Hello World</sw-password-field>
```
After:
```html
<mt-password-field>Hello World</mt-password-field>
```

### "mt-password-field" has no property "value" anymore
Replace all occurrences of the "value" prop with "modelValue"

Before:
```html
<sw-password-field value="Hello World" />
```
After:
```html
<mt-password-field modelValue="Hello World" />
```

### "mt-password-field" v-model:value is deprecated
Replace all occurrences of the "v-model:value" directive with "v-model"

Before:
```html
<sw-password-field v-model:value="myValue" />
```
After:
```html
<mt-password-field v-model="myValue" />
```

### "mt-password-field" has no property "size" with value "medium" anymore
Replace all occurrences of the "size" prop with "default"

Before:
```html
<sw-password-field size="medium" />
```
After:
```html
<mt-password-field size="default" />
```

### "mt-password-field" has no property "isInvalid" anymore
Remove all occurrences of the "isInvalid" prop

Before:
```html
<sw-password-field isInvalid />
```
After:
```html
<mt-password-field />
```

### "mt-password-field" has no event "update:value" anymore
Replace all occurrences of the "update:value" event with "update:modelValue"

Before:
```html
<sw-password-field @update:value="updateValue" />
```

After:
```html
<mt-password-field @update:modelValue="updateValue" />
```

### "mt-password-field" has no event "base-field-mounted" anymore
Remove all occurrences of the "base-field-mounted" event

Before:
```html
<sw-password-field @base-field-mounted="onFieldMounted" />
```
After:
```html
<mt-password-field />
```

### "mt-password-field" has no slot "label" anymore
Remove all occurrences of the "label" slot. The slot content should be moved to the "label" prop. Only string values are supported. Other slot content is not supported
anymore.

Before:
```html
<sw-password-field>
    <template #label>
        My Label
    </template>
</sw-password-field>
```
After:
```html
<mt-password-field label="My label">
</mt-password-field>
```

### "mt-password-field" has no slot "hint" anymore
Remove all occurrences of the "hint" slot. The slot content should be moved to the "hint" prop. Only string values are supported. Other slot content is not supported

Before:
```html
<sw-password-field>
    <template #hint>
        My Hint
    </template>
</sw-password-field>
```
After:
```html
<mt-password-field hint="My hint">
</mt-password-field>
```
