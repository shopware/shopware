[titleEn]: <>(Bulk functions for sw-data-grid and sw-entity-listing)

To handle multiple table entries at once, `sw-data-grid` and `sw-entity-listing` received the possibility to perform
bulk actions. Switching the `showSelection` to `true` automatically shows the bulk action bar after the first checkbox
selection. While `sw-entity-listing` provides bulk deletion by default, you have to slot it in `sw-data-grid` to add
functionality.

### Examples

#### Setting up the bulk action bar

Add `a` links with `link` class or optional variant classes like `link-danger` or `link-warning` inside the `bulk` slot.

```html
<template #bulk>
    <a class="link link-danger" @click="showBulkDeleteModal = true">
        {{ $tc('global.default.delete') }}
    </a>

    <a class="link" @click="showBulkAwesomeModal = true">
        Awesome feature
    </a>
</template>
```

#### Adding bulk modals

Typical modal using the `#bulk-modal` slot with content as you already know e.g. from `sw-context-menu`

```html
<template #bulk-modals>
    <sw-modal v-if="showBulkDeleteModal"
              @modal-close="showBulkDeleteModal = false"
              :title="$tc('global.entity-components.deleteTitle')"
              variant="small">
        <p class="sw-data-grid__confirm-bulk-delete-text">
            {{ $tc('global.entity-components.deleteMessage', selectionCount, { count: selectionCount }) }}
        </p>

        <template #modal-footer>
            <sw-button @click="showBulkDeleteModal = false" size="small">
                {{ $tc('global.default.cancel') }}
            </sw-button>

            <sw-button @click="deleteItems" variant="primary" size="small" :isLoading="isBulkLoading">
                {{ $tc('global.default.delete') }}
            </sw-button>
        </template>
    </sw-modal>
</template>
```

#### Bulk delete functionality in JS

It's recommended to clear the selection during `getList()` using `this.selection = {};`

```js
deleteItems() {
    this.isBulkLoading = true;
    const promises = [];

    Object.values(this.selection).forEach((selectedProxy) => {
        promises.push(this.repository.delete(selectedProxy.id, this.items.context));
    });

    return Promise.all(promises).then(() => {
        return this.deleteItemsFinish();
    }).catch(() => {
        return this.deleteItemsFinish();
    });
},

deleteItemsFinish() {
    this.resetSelection();
    this.isBulkLoading = false;
    this.showBulkDeleteModal = false;

    return this.doSearch();
},

resetSelection() {
    this.$refs.swMyGrid.selection = {};
    this.$refs.swMyGrid.allSelectedChecked = false;
},
```

#### Updating your item total

This update also adds an `update-records` event, so you can keep track of your item total. It comes with the complete
`items` in the grid, but mostly you will use it for your total.

```js
updateTotal({ total }) {
    this.total = total;
},
```
