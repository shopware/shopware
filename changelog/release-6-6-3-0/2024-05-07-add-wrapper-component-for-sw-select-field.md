---
title: Add wrapper component for sw-select-field
issue: NEXT-34279
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-select-field
* Added codemods (ESLint rules) for converting sw-select-field to mt-select
___
# Next Major Version Changes

## Removal of "sw-select-field":
The old "sw-select-field" component will be removed in the next major version. Please use the new "mt-select" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-select" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-select-field" with "mt-select".

Following changes are necessary:

### "sw-select-field" is removed
Replace all component names from "sw-select-field" with "mt-select"

Before:
```html
<sw-select-field />
```
After:
```html
<mt-select />
```

### "sw-select-field" prop "value" was renamed to "modelValue"
Replace all occurrences of the prop "value" with "modelValue"

Before:
```html
<sw-select-field :value="selectedValue" />
```

After:
```html
<mt-select :modelValue="selectedValue" />
```

### "sw-select-field" the "v-model:value" was renamed to "v-model"
Replace all occurrences of the "v-model:value" directive with "v-model"

Before:
```html
<sw-select-field v-model:value="selectedValue" />
```

After:
```html
<mt-select v-model="selectedValue" />
```

### "sw-select-field" the prop "options" expect a different format
The prop "options" now expects an array of objects with the properties "label" and "value". The old format with "name" and "id" is not supported anymore.

Before:
```html
<sw-select-field :options="[ { name: 'Option 1', id: 1 }, { name: 'Option 2', id: 2 } ]" />
```

After:
```html
<mt-select :options="[ { label: 'Option 1', value: 1 }, { label: 'Option 2', value: 2 } ]" />
```

### "sw-select-field" the prop "aside" was removed
The prop "aside" was removed without replacement.

Before:
```html
<sw-select-field :aside="true" />
```

After:
```html
<mt-select />
```

### "sw-select-field" the default slot was removed
The default slot was removed. The options are now passed via the "options" prop.

Before:
```html
<sw-select-field>
    <option value="1">Option 1</option>
    <option value="2">Option 2</option>
</sw-select-field>
```

After:
```html
<mt-select :options="[ { label: 'Option 1', value: 1 }, { label: 'Option 2', value: 2 } ]" />
```

### "sw-select-field" the label slot was removed
The label slot was removed. The label is now passed via the "label" prop.

Before:
```html
<sw-select-field>
    <template #label>
        My Label
    </template>
</sw-select-field>
```

After:
```html
<mt-select label="My Label" />
```

### "sw-select-field" the event "update:value" was renamed to "update:modelValue"
The event "update:value" was renamed to "update:modelValue"

Before:
```html
<sw-select-field @update:value="onUpdateValue" />
```

After:
```html
<mt-select @update:modelValue="onUpdateValue" />
```
