---
title: Add wrapper component for sw-popover
issue: NEXT-34293
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added wrapper component for sw-popover
* Added codemods (ESLint rules) for converting sw-card to mt-floating-ui
___
# Next Major Version Changes

## Removal of "sw-popover":
The old "sw-popover" component will be removed in the next major version. Please use the new "mt-floating-ui" component instead.

We will provide you with a codemod (ESLint rule) to automatically convert your codebase to use the new "mt-floating-ui" component. This component is much different from the old "sw-popover" component, so the codemod will not be able to convert all occurrences. You will have to manually adjust some parts of your codebase. For this you can look at the Storybook documentation for the Meteor Component Library.

If you don't want to use the codemod, you can manually replace all occurrences of "sw-popover" with "mt-floating-ui".

Following changes are necessary:

### "sw-popover" is removed
Replace all component names from "sw-popover" with "mt-floating-ui"

Before:
```html
<sw-popover />
```
After:
```html
<mt-floating-ui />
```

### "mt-floating-ui" has no property "zIndex" anymore
The property "zIndex" is removed without a replacement.

Before:
```html
<sw-popover :zIndex="myZIndex" />
```
After:
```html
<mt-floating-ui />
```

### "mt-floating-ui" has no property "resizeWidth" anymore
The property "resizeWidth" is removed without a replacement.

Before:
```html
<sw-popover :resizeWidth="myWidth" />
```

After:
```html
<mt-floating-ui />
```

### "mt-floating-ui" has no property "popoverClass" anymore
The property "popoverClass" is removed without a replacement.

Before:
```html
<sw-popover popoverClass="my-class" />
```
After:
```html
<mt-floating-ui />
```

### "mt-floating-ui" is not open by default anymore
The "open" property is removed. You have to control the visibility of the popover by yourself with the property "isOpened".

Before:
```html
<sw-popover />
```
After:
```html
<mt-floating-ui :isOpened="myVisibility" />
```
