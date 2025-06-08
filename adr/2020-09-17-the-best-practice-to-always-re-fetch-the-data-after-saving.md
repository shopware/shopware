---
title: The best-practice to always re-fetch the data after saving
date: 2020-09-17
area: administration
tags: [administration, data-handling]
---

## Context

We should always re-fetch the entity data after saving within admin pages.

## Decision

Reload the data after each saving progress to ensure the user will work only the latest data.

When you save data without reloading the entity, then you need to re-assign the values. But you can't be sure, that these values are the latest ones, because of possible data inconsistency during the saving process. That's why re-fetching data is always important for further CRUD operations.

For example:

```html
<!-- we change the status by click to switch for example -->
<sw-switch-field
    v-model="data.status"
    :label="$tc('sw-review.detail.labelStatus')">
</sw-switch-field>

<!-- we will save data with onSave method -->
<sw-button-process @click="onSave">
    {{ $tc('global.default.save') }}
</sw-button-process>
```

```javascript

// This method for button save
onSave() {
    this.repository.save(this.data, Shopware.Context.api).then(() => {
        // We should add the method to re-fetch the entity data after save success here
        this.loadEntityData();
    });
},

// This method to re-fetch the data
loadEntityData() {
    const criteria = new Criteria();
    const context = { ...Shopware.Context.api, inheritance: true };

    this.repository.get(this.data.id, context, criteria).then((data) => {
        this.data = data;
    });
},
```

## Consequences

Consistent and CRUD-ready data in your administration.
