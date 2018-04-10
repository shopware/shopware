import { Component } from 'src/core/shopware';
import dom from 'src/core/service/utils/dom.utils';
import template from './sw-grid.html.twig';
import './sw-grid.less';

Component.register('sw-grid', {
    template,

    props: {
        items: {
            type: Array,
            required: false,
            default: null
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
        },

        isFullpage: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            columns: [],
            selection: {},
            scrollbarOffset: 0,
            editing: []
        };
    },

    computed: {
        sort() {
            return this.sortBy;
        },

        sortDir() {
            return this.sortDirection;
        },

        sizeClass() {
            return `sw-grid--${this.variant}`;
        },

        gridClasses() {
            return {
                'sw-grid--sidebar': this.sidebar,
                'sw-grid--fullpage': this.isFullpage,
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

            return {
                'grid-template-columns': flex.trim()
            };
        }
    },

    updated() {
        this.setScrollbarOffset();
    },

    created() {
        this.registerInlineEditingEvents();
    },

    methods: {
        registerInlineEditingEvents() {
            this.$on('sw-row-inline-edit-start', this.inlineEditingStart);

            this.$on('sw-row-inline-edit-cancel', this.disableActiveInlineEditing);
        },

        inlineEditingStart(id) {
            this.editing.push(id);
        },

        disableActiveInlineEditing(id) {
            this.editing = this.editing.filter((item) => {
                return item !== id;
            });
        },

        selectAll(selected) {
            this.selection = {};

            if (selected) {
                this.items.forEach((item) => {
                    this.selection[item.id] = item;
                });
            }
        },

        getSelection() {
            return this.selection;
        },

        selectItem(selected, item) {
            const selection = this.selection;

            if (selected === true) {
                selection[item.id] = item;
            } else if (!selected && selection[item.id]) {
                delete this.selection[item.id];
            }

            this.selection = {};
            this.selection = selection;
        },

        isSelected(itemId) {
            return typeof this.selection[itemId] !== 'undefined';
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
        },

        onGridCellClick(event, column) {
            if (!column.sortable) {
                return;
            }

            this.$emit('sw-grid-disable-inline-editing');
            this.$emit('sort-column', column);
        },

        setScrollbarOffset() {
            this.scrollbarOffset = dom.getScrollbarWidth(this.$refs.swGridBody);
        }
    }
});
