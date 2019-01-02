import { Component } from 'src/core/shopware';

Component.extend('sw-condition-value', 'sw-select', {
    methods: {
        loadSelections() {
            this.isLoading = true;
            console.log('loadSelection', this.value);
            if (this.multi) {
                if (!this.value) {
                    return;
                }

                this.value.forEach((id) => {
                    this.selections.push(this.store.getById(id));
                });
                this.isLoading = false;
            } else {
                // return if the value is not set yet(*note the watcher on value)
                if (!this.value) {
                    return;
                }
                this.singleSelection = this.store.getById(this.value);
                this.isLoading = false;
            }
        },

        emitChanges(items) {
            const itemIds = items.map((item) => item.id);
            this.$emit('input', itemIds);
        }
    }
});
