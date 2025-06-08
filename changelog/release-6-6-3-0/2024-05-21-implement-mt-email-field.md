---
title: Implement mt-email-field
issue: NEXT-34294
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Added wrapper component for sw-email-field
* Added codemods (ESLint rules) for converting sw-email-field to mt-email-field
___
# Next Major Version Changes

## Removal of "sw-email-field":
The old "sw-email-field" component will be removed in the next major version. Please use the new "mt-email-field" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-email-field" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-email-field" with "mt-email-field".

Following changes are necessary:

### "sw-email-field" is removed
Replace all component names from "sw-email-field" with "mt-email-field"

Before:
```html
<sw-email-field>Hello World</sw-email-field>
```
After:
```html
<mt-email-field>Hello World</mt-email-field>
```

### "mt-email-field" has no property "value" anymore
Replace all occurrences of the "value" prop with "modelValue"

Before:
```html
<mt-email-field value="Hello World" />
```
After:
```html
<mt-email-field modelValue="Hello World" />
```

### "mt-email-field" v-model:value is deprecated
Replace all occurrences of the "v-model:value" directive with "v-model"

Before:
```html
<mt-email-field v-model:value="myValue" />
```
After:
```html
<mt-email-field v-model="myValue" />
```

### "mt-email-field" has no property "size" with value "medium" anymore
Replace all occurrences of the "size" prop with "default"

Before:
```html
<mt-email-field size="medium" />
```
After:
```html
<mt-email-field size="default" />
```

### "mt-email-field" has no property "isInvalid" anymore
Remove all occurrences of the "isInvalid" prop

Before:
```html
<mt-email-field isInvalid />
```
After:
```html
<mt-email-field />
```

### "mt-email-field" has no property "aiBadge" anymore
Remove all occurrences of the "aiBadge" prop

Before:
```html
<mt-email-field aiBadge />
```
After:
```html
<mt-email-field />
```

### "mt-email-field" has no event "update:value" anymore
Replace all occurrences of the "update:value" event with "update:modelValue"

Before:
```html
<mt-email-field @update:value="updateValue" />
```

After:
```html
<mt-email-field @update:modelValue="updateValue" />
```

### "mt-email-field" has no event "base-field-mounted" anymore
Remove all occurrences of the "base-field-mounted" event

Before:
```html
<mt-email-field @base-field-mounted="onFieldMounted" />
```
After:
```html
<mt-email-field />
```

### "mt-email-field" has no slot "label" anymore
Remove all occurrences of the "label" slot. The slot content should be moved to the "label" prop. Only string values are supported. Other slot content is not supported
anymore.

Before:
```html
<mt-email-field>
    <template #label>
        My Label
    </template>
</mt-email-field>
```
After:
```html
<mt-email-field label="My label">
</mt-email-field>
```
