import 'src/app/component/atom/grid/sw-grid-row/sw-grid-row.less';
import template from 'src/app/component/atom/grid/sw-grid-row/sw-grid-row.html.twig';
import swRowGridEditing from 'src/app/component/atom/grid/sw-grid-row-editing/sw-grid-row-editing';

export default Shopware.Component.register('sw-grid-row', {
    inject: ['eventEmitter'],
    props: ['editable', 'item'],

    data() {
        return {
            isEditing: false,
            editingItems: []
        };
    },

    components: {
        'sw-grid-row-editing': swRowGridEditing
    },

    methods: {
        startEditing(event) {
            const nodeType = event.target.nodeName.toLowerCase();
            event.preventDefault();

            if (!this.editable || this.isEditing || ['input', 'button'].indexOf(nodeType) !== -1) {
                return false;
            }

            // Collect the items for the row editing
            const editingItems = [];
            this.$children.forEach((item) => {
                let val = this.item[item.dataIndex];

                if (val === undefined) {
                    val = item.$el.innerHTML;
                }

                editingItems.push({
                    editor: item.editor || 'none',
                    colWidth: item.colWidth,
                    dataIndex: item.dataIndex,
                    value: val
                });
            });

            this.editingItems = editingItems;

            // Switch the property to display the row editing row
            this.isEditing = true;

            return true;
        },

        saveEditing(items) {
            const editedItems = {};

            // Sanitize the items
            items.forEach((item) => {
                if (!item.dataIndex) {
                    return;
                }
                editedItems[item.dataIndex] = item.value;
            });

            this.eventEmitter.emit('save-editing', {
                id: this.$vnode.key,
                items: editedItems
            });

            this.isEditing = false;
        }
    },
    template
});
