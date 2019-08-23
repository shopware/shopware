const { Component } = Shopware;

/**
 * @private
 */
Component.extend('sw-condition-value', 'sw-select', {
    watch: {
        value() {
            if (this.multi) {
                this.loadSelected();
            }
        }
    },

    methods: {
        loadSelected() {
            this.isLoading = true;
            if (this.multi) {
                if (!this.value) {
                    return;
                }

                this.selections = [];
                this.value.forEach((id) => {
                    const item = this.store.getById(id);
                    this.selections.push(item);
                    this.selected.push(item);
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
