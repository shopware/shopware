---
title: Add wrapper component for sw-text-field
issue: NEXT-34273
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-text-field
* Added codemods (ESLint rules) for converting sw-card to mt-text-field
___
# Next Major Version Changes

## Removal of "sw-text-field":
The old "sw-text-field" component will be removed in the next major version. Please use the new "mt-text-field" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-text-field" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-text-field" with "mt-text-field".

Following changes are necessary:

### "sw-text-field" is removed
Replace all component names from "sw-text-field" with "mt-text-field"

Before:
```html
<sw-text-field>Hello World</sw-text-field>
```
After:
```html
<mt-text-field>Hello World</mt-text-field>
```

### "mt-text-field" has no property "value" anymore
Replace all occurrences of the "value" prop with "modelValue"

Before:
```html
<mt-text-field value="Hello World" />
```
After:
```html
<mt-text-field modelValue="Hello World" />
```

### "mt-text-field" v-model:value is deprecated
Replace all occurrences of the "v-model:value" directive with "v-model"

Before:
```html
<mt-text-field v-model:value="myValue" />
```
After:
```html
<mt-text-field v-model="myValue" />
```

### "mt-text-field" has no property "size" with value "medium" anymore
Replace all occurrences of the "size" prop with "default"

Before:
```html
<mt-text-field size="medium" />
```
After:
```html
<mt-text-field size="default" />
```

### "mt-text-field" has no property "isInvalid" anymore
Remove all occurrences of the "isInvalid" prop

Before:
```html
<mt-text-field isInvalid />
```
After:
```html
<mt-text-field />
```

### "mt-text-field" has no property "aiBadge" anymore
Remove all occurrences of the "aiBadge" prop

Before:
```html
<mt-text-field aiBadge />
```
After:
```html
<mt-text-field />
```

### "mt-text-field" has no event "update:value" anymore
Replace all occurrences of the "update:value" event with "update:modelValue"

Before:
```html
<mt-text-field @update:value="updateValue" />
```

After:
```html
<mt-text-field @update:modelValue="updateValue" />
```

### "mt-text-field" has no event "base-field-mounted" anymore
Remove all occurrences of the "base-field-mounted" event

Before:
```html
<mt-text-field @base-field-mounted="onFieldMounted" />
```
After:
```html
<mt-text-field />
```

### "mt-text-field" has no slot "label" anymore
Remove all occurrences of the "label" slot. The slot content should be moved to the "label" prop. Only string values are supported. Other slot content is not supported
anymore.

Before:
```html
<mt-text-field>
    <template #label>
        My Label
    </template>
</mt-text-field>
```
After:
```html
<mt-text-field label="My label">
</mt-text-field>
```
