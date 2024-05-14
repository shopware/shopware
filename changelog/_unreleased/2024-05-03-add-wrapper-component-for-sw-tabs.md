---
title: Add wrapper component for sw-tabs
issue: NEXT-34280
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-tabs
* Added codemods (ESLint rules) for converting sw-tabs to mt-tabs
___
# Next Major Version Changes

## Removal of "sw-tabs":
The old "sw-tabs" component will be removed in the next major version. Please use the new "mt-tabs" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-tabs" component. In this specific component it cannot convert anything correctly, because the new "mt-tabs" component has a different API. You have to manually check and solve every "TODO" comment created by the codemod.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-tabs" with "mt-tabs".

Following changes are necessary:

### "sw-tabs" is removed
Replace all component names from "sw-tabs" with "mt-tabs"

Before:
```html
<sw-tabs />
```
After:
```html
<mt-tabs />
```

### "sw-tabs" wrong "default" slot usage will be replaced with "items" property
You need to replace the "default" slot with the "items" property. The "items" property is an array of objects which are used to render the tabs. Using the "sw-tabs-item" component is not needed anymore.

Before:
```html
<sw-tabs>
    <template #default="{ active }">
        <sw-tabs-item name="tab1">Tab 1</sw-tabs-item>
        <sw-tabs-item name="tab2">Tab 2</sw-tabs-item>
    </template>
</sw-tabs>
```

After:
```html
<mt-tabs :items="[
    {
        'label': 'Tab 1',
        'name': 'tab1'
    },
    {
        'label': 'Tab 2',
        'name': 'tab2'
    }
]">
</mt-tabs>
```

### "sw-tabs" wrong "content" slot usage - content should be set manually outside the component
The content slot is not supported anymore. You need to set the content manually outside the component. You can use the "new-item-active" event to get the active item and set it to a variable. Then you can use this variable anywere in your template.

Before:
```html
<sw-tabs>
    <template #content="{ active }">
        The current active item is {{ active }}
    </template>
</sw-tabs>
```

After:
```html
<!-- setActiveItem need to be defined -->
<mt-tabs @new-item-active="setActiveItem"></mt-tabs>

The current active item is {{ activeItem }}
```

### "sw-tabs" property "isVertical" was renamed to "vertical"
Before:
```html
<sw-tabs is-vertical />
```

After:
```html
<mt-tabs vertical />
```

### "sw-tabs" property "alignRight" was removed
Before:
```html
<sw-tabs align-right />
```

After:
```html
<mt-tabs />
```

