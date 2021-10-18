---
title: Fix order of slots in category cms edit tab
issue: NEXT-5966
---
# Administration
* Added position constants of CMS slots to `administration/src/module/sw-cms/constant/sw-cms.constant.js` 
* Added `createdComponent` method to `administration/src/module/sw-cms/component/sw-cms-page-form/index.js`, which sorts the slots via position constants for category detail / page cms view
___
# Upgrade Information
## Position constants for CMS slots

Before, the slots had no order in the form overviews of category detail or the page view of CMS templates. This was due to a lack of sort values of slots.
But now every slot type (`cms_slot.slot`) has a specific positiong value, to be found in `administration/src/module/sw-cms/constant/sw-cms.constant.js`. 
When adding own blocks with new slot templates, plugin developers should be aware of that and add their own slot position values and extend
`administration/src/module/sw-cms/component/sw-cms-page-form/index.js::slotPositions()` to get their own values into the constants.

```js
slotPositions() {
    const myPositions = {
        'my-left-top-slot': 250,
        'my-very-left-center-slot': 950
    };
    
    return {
        ...myPositions,
        ...this.$super('slotPositions'),
    };
},
```

Please be careful and chose the numbers wisely. The lower the number, the earlier the slot will appear. **Do not override existing properties to avoid side effects!**
Therefore, the "left namespace" is described by numbers intervals of 0 to 999, center 1000 to 1999, right 2000 to 2999 and everything else 3000 to 4999, with 5000 being the default value to be used when no own values are provided.