---
title: Add wrapper component for sw-number-field
issue: NEXT-34275
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-number-field
* Added codemods (ESLint rules) for converting sw-number to mt-number-field
___
# Next Major Version Changes

## Removal of "sw-number-field":
The old "sw-number-field" component will be removed in the next major version. Please use the new "mt-number-field" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-number-field" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-number-field" with "mt-number-field".

Following changes are necessary:

### "sw-number-field" is removed
Replace all component names from "sw-number-field" with "mt-number-field"

Before:
```html
<sw-number-field />
```
After:
```html
<mt-number-field />
```

### "mt-number-field" has no property "value" anymore
Replace all occurrences of the "value" prop with "modelValue"

Before:
```html
<mt-number-field :value="5" />
```
After:
```html
<mt-number-field :modelValue="5" />
```

### "mt-number-field" v-model:value is deprecated
Replace all occurrences of the "v-model:value" directive with the combination of `:modelValue` and `@change`

Before:
```html
<mt-number-field v-model:value="myValue" />
```
After:
```html
<mt-number-field :modelValue="myValue" @change="myValue = $event" />
```

### "mt-number-field" label slot is deprecated
Replace all occurrences of the "label" slot with the "label" prop

Before:
```html
<mt-number-field>
    <template #label>
        My Label
    </template>
</mt-number-field>
```

After:
```html
<mt-number-field label="My Label" />
```

### "mt-number-field" update:value event is deprecated
Replace all occurrences of the "update:value" event with the "change" event

Before:
```html
<mt-number-field @update:value="updateValue" />
```
After:
```html
<mt-number-field @change="updateValue" />
```
