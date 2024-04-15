---
title: Add wrapper component for sw-switch-field
issue: NEXT-34274
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-switch-field
* Added codemods (ESLint rules) for converting sw-card to mt-switch
___
# Next Major Version Changes

## Removal of "sw-switch-field":
The old "sw-switch-field" component will be removed in the next major version. Please use the new "mt-switch" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-switch" component.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-switch-field" with "mt-switch".

Following changes are necessary:

### "sw-switch-field" is removed
Replace all component names from "sw-switch-field" with "mt-switch".

Before:
```html
<sw-switch-field>Hello World</sw-switch-field>
```
After:
```html
<mt-switch>Hello World</mt-switch>
```

### "mt-switch" has no "noMarginTop" prop anymore
Replace all occurrences of the "noMarginTop" prop with "removeTopMargin".

Before:
```html
<mt-switch noMarginTop />
```
After:
```html
<mt-switch removeTopMargin />
```

### "mt-switch" has no "size" prop anymore
Remove all occurrences of the "size" prop.

Before:
```html
<mt-switch size="small" />
```

After:
```html
<mt-switch />
```

### "mt-switch" has no "id" prop anymore
Remove all occurrences of the "id" prop.

Before:
```html
<mt-switch id="example-identifier" />
```

After:
```html
<mt-switch />
```

### "mt-switch" has no "value" prop anymore
Replace all occurrences of the "value" prop with "checked".

Before:
```html
<mt-switch value="true" />
```

After:
```html
<mt-switch checked="true" />
```

### "mt-switch" has no "ghostValue" prop anymore
Remove all occurrences of the "ghostValue" prop.

Before:
```html
<mt-switch ghostValue="true" />
```

After:
```html
<mt-switch />
```

### "mt-switch" has no "padded" prop anymore
Remove all occurrences of the "padded" prop. Use CSS styling instead.

Before:
```html
<mt-switch padded="true" />
```

After:
```html
<mt-switch />
```

### "mt-switch" has no "partlyChecked" prop anymore
Remove all occurrences of the "partlyChecked" prop.

Before:
```html
<mt-switch partlyChecked="true" />
```

After:
```html
<mt-switch />
```

### "mt-switch" has no "label" slot anymore
Replace all occurrences of the "label" slot with the "label" prop.

Before:
```html
<mt-switch>
    <template #label>
        Foobar
    </template>
</mt-switch>
```

After:
```html
<mt-switch label="Foobar">
</mt-switch>
```

### "mt-switch" has no "hint" slot anymore
Remove all occurrences of the "hint" slot.

Before:
```html
<mt-switch>
    <template #hint>
        Foobar
    </template>
</mt-switch>
```

After:
```html
<mt-switch>
    <!-- Slot "hint" was removed with no replacement. -->
</mt-switch>
```
