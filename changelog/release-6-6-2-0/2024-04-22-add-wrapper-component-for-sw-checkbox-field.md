---
title: Add wrapper component for sw-checkbox-field
issue: NEXT-34277
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-checkbox-field
* Added codemods (ESLint rules) for converting sw-checkbox-field to mt-checkbox
___
# Next Major Version Changes

## Removal of "sw-checkbox-field":
The old "sw-checkbox-field" component will be removed in the next major version. Please use the new "mt-checkbox" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-checkbox" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-checkbox-field" with "mt-checkbox".

Following changes are necessary:

### "sw-checkbox-field" is removed
Replace all component names from "sw-checkbox-field" with "mt-checkbox"

Before:
```html
<sw-checkbox-field />
```
After:
```html
<mt-checkbox />
```

### "mt-checkbox" has no property "value" anymore
Replace all occurrences of the "value" prop with "checked"

Before:
```html
<sw-checkbox-field :value="myValue" />
```
After:
```html
<mt-checkbox :checked="myValue" />
```

### "mt-checkbox" has changed the v-model usage
Replace all occurrences of the "v-model" directive with "v-model:checked"

Before:
```html
<sw-checkbox-field v-model="isCheckedValue" />
```
After:
```html
<mt-checkbox v-model:checked="isCheckedValue" />
```

### "mt-checkbox" has changed the slot "label" usage
Replace all occurrences of the "label" slot with the "label" prop

Before:
```html
<sw-checkbox-field>
    <template #label>
        Hello Shopware
    </template>
</sw-checkbox-field>
```

After:
```html
<mt-checkbox label="Hello Shopware">
</mt-checkbox>
```

### "mt-checkbox" has removed the slot "hint"
The "hint" slot was removed without replacement

Before:
```html
<sw-checkbox-field>
    <template v-slot:hint>
        Hello Shopware
    </template>
</sw-checkbox-field>
```

### "mt-checkbox" has removed the property "id"
The "id" prop was removed without replacement

Before:
```html
<sw-checkbox-field id="checkbox-id" />
```

### "mt-checkbox" has removed the property "ghostValue"
The "ghostValue" prop was removed without replacement

Before:
```html
<sw-checkbox-field ghostValue="yes" />
```

### "mt-checkbox" has changed the property "partlyChecked"
Replace all occurrences of the "partlyChecked" prop with "partial"

Before:
```html
<sw-checkbox-field partlyChecked />
```
After:
```html
<mt-checkbox partial />
```

### "mt-checkbox" has removed the property "padded"
The "padded" prop was removed without replacement

Before:
```html
<sw-checkbox-field padded />
```

### "mt-checkbox" has changed the event "update:value"
Replace all occurrences of the "update:value" event with "update:checked"

Before:
```html
<sw-checkbox-field @update:value="updateValue" />
```
After:
```html
<mt-checkbox @update:checked="updateValue" />
```
