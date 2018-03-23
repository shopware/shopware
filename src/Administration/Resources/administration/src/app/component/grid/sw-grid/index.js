import { Component } from 'src/core/shopware';
import './sw-grid.less';
import template from './sw-grid.html.twig';

Component.register('sw-grid', {
    template,

    data() {
        return {
            columns: [],
            selected: false
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

        variant: {
            type: String,
            required: false,
            default: 'normal'
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
        },

        sortBy: {
            type: String,
            required: false
        },

        sortDirection: {
            type: String,
            required: false,
            default: 'ASC'
        }
    },

    computed: {
        sort() {
            return this.sortBy;
        },
        sortDir() {
            return this.sortDirection;
        },
        sizeClass() {
            return `sw-grid__${this.variant}`;
        },
        gridClasses() {
            return {
                'sw-grid': true,
                'sw-grid--sidebar': this.sidebar,
                [this.sizeClass]: true
            };
        },
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
            this.selected = selected;
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
    }
});
