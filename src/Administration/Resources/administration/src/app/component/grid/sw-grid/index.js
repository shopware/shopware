import './sw-grid.less';
import template from './sw-grid.html.twig';

export default Shopware.Component.register('sw-grid', {

    data() {
        return {
            columns: []
        };
    },

    props: {
        items: {
            type: Array,
            required: false,
            default: null
        },

        actions: {
            type: Array,
            required: false,
            default() {
                return ['edit', 'delete', 'duplicate'];
            }
        },

        selectable: {
            type: Boolean,
            required: false,
            default: true
        },

        sidebar: {
            type: Boolean,
            required: false,
            default: false
        },

        header: {
            type: Boolean,
            required: false,
            default: true
        },

        pagination: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        columnFlex() {
            let flex = (this.selectable === true) ? '50px ' : '';

            this.columns.forEach((column) => {
                if (`${parseInt(column.flex, 10)}` === column.flex) {
                    flex += `${column.flex}fr `;
                } else {
                    flex += `${column.flex} `;
                }
            });

            if (this.actions.length > 0) {
                flex += '140px';
            }

            return {
                'grid-template-columns': flex.trim()
            };
        }
    },

    watch: {
        items(items) {
            items.forEach((item) => {
                if (!item.selected) {
                    this.$set(item, 'selected', false);
                }
            });
        }
    },

    methods: {
        selectAll(selected) {
            this.items.forEach((item) => {
                this.$set(item, 'selected', selected);
            });
        },

        getSelection() {
            return this.items.filter((item) => {
                return item.selected;
            });
        },

        getScrollBarWidth() {
            if (!this.$el) {
                return 0;
            }

            const gridBody = this.$el.getElementsByClassName('sw-grid--body')[0];

            if (gridBody.offsetWidth && gridBody.clientWidth) {
                return gridBody.offsetWidth - gridBody.clientWidth;
            }

            return 0;
        }
    },

    template
});
