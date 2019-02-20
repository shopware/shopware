export default {
    name: 'sw-condition-value',
    extendsFrom: 'sw-select',

    watch: {
        value() {
            if (this.multi) {
                this.loadSelections();
            }
        }
    },

    methods: {
        loadSelections() {
            this.isLoading = true;
            if (this.multi) {
                if (!this.value) {
                    return;
                }

                this.selections = [];
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
};
