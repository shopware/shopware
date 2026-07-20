---
title: Implemented mt-empty-stage component
---
# Administration
* Changed the old `sw-empty-state` to the new `mt-empty-state`
___
# Next Major Version Changes
## Removal of "sw-empty-state"
* The old `sw-empty-state` component will be removed in the next major version. Please use the new `mt-empty-state` component instead.

Before:
```html
<sw-empty-state title="short title" subline="longer subline" />
```
After:
```html
<mt-empty-state title="short title" description="longer description"/>
```
