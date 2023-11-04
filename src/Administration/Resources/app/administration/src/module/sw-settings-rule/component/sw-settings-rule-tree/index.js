/**
 * @private
 * @package business-ops
 */
export default {

    data() {
        return {
            selection: {},
        };
    },

    methods: {
        checkItem(item) {
            this.$super('checkItem', item);

            if (item.checked) {
                this.selection[item.id] = item;
            } else {
                delete this.selection[item.id];
            }

            this.$emit('check-item', this.selection);
        },

        getTreeItems(parentId) {
            const checkedItems = Object.keys(this.checkedElements);
            const items = this.$super('getTreeItems', parentId);

            items.forEach((item) => {
                const isChecked = checkedItems.includes(item.id);

                if (isChecked) {
                    item.checked = isChecked;
                }
            });

            return items;
        },
    },
};
