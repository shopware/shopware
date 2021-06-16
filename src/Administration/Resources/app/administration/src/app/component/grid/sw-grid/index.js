import template from './sw-grid.html.twig';
import './sw-grid.scss';

const { Component } = Shopware;
const { dom } = Shopware.Utils;

/**
 * @public
 * @status ready
 * @example-type static
 * @component-example
 * <sw-grid :items="[
 *     {company:'Wordify',name:'Portia Jobson'},
 *     {company:'Twitternation',name:'Baxy Eardley'},
 *     {company:'Skidoo',name:'Arturo Staker'},
 *     {company:'Meetz',name:'Dalston Top'},
 *     {company:'Photojam',name:'Neddy Jensen'}]">
 *     <template #columns="{ item }">
 *         <sw-grid-column flex="minmax(200px, 1fr)" label="Company">
 *             <strong>{{ item.company }}</strong>
 *         </sw-grid-column>
 *
 *         <sw-grid-column flex="minmax(200px, 1fr)" label="Full name">
 *             {{ item.name }}
 *         </sw-grid-column>
 *     </template>
 * </sw-grid>
 */
Component.register('sw-grid', {
    template,

    props: {
        items: {
            type: Array,
            required: false,
            default: null,
        },

        selectable: {
            type: Boolean,
            required: false,
            default: true,
        },

        variant: {
            type: String,
            required: false,
            default: 'normal',
        },

        header: {
            type: Boolean,
            required: false,
            default: true,
        },

        sortBy: {
            type: String,
            required: false,
            default: null,
        },

        sortDirection: {
            type: String,
            required: false,
            default: 'ASC',
        },

        isFullpage: {
            type: Boolean,
            required: false,
            default: false,
        },

        table: {
            type: Boolean,
            required: false,
            default: false,
        },

        allowInlineEdit: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            columns: [],
            selection: {},
            scrollbarOffset: 0,
            editing: null,
            allSelectedChecked: false,
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

        hasPaginationSlot() {
            return !!this.$slots.pagination;
        },

        gridClasses() {
            return {
                'sw-grid--fullpage': this.isFullpage,
                'sw-grid--table': this.table,
                [this.sizeClass]: true,
            };
        },

        gridContentClasses() {
            return {
                'sw-grid__content--header': this.header,
                'sw-grid__content--pagination': this.hasPaginationSlot,
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
                'grid-template-columns': flex.trim(),
            };
        },
    },

    updated() {
        this.updatedComponent();
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const that = this;

            this.registerInlineEditingEvents();

            this.$device.onResize({
                listener() {
                    that.setScrollbarOffset();
                },
                component: this,
            });
        },

        updatedComponent() {
            this.setScrollbarOffset();
        },

        onInlineEditFinish(item) {
            this.editing = null;
            this.$emit('inline-edit-finish', item);
        },

        onInlineEditStart(item) {
            this.$emit('inline-edit-start', item);
        },

        registerInlineEditingEvents() {
            this.$on('sw-row-inline-edit-start', this.inlineEditingStart);
            this.$on('sw-row-inline-edit-cancel', this.disableActiveInlineEditing);
        },

        inlineEditingStart(id) {
            if (this.editing != null) {
                this.$emit('sw-grid-disable-inline-editing', this.editing);
            }

            this.editing = id;
        },

        disableActiveInlineEditing() {
            this.editing = null;
        },

        selectAll(selected) {
            this.selection = {};

            this.items.forEach((item) => {
                if (this.isSelected(item.id) !== selected) {
                    this.selectItem(selected, item);
                }
            });

            this.allSelectedChecked = selected;
            this.$emit('sw-grid-select-all', this.selection);
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

            this.checkSelection();
            this.$emit('sw-grid-select-item', this.selection, item, selected);
        },

        isSelected(itemId) {
            return typeof this.selection[itemId] !== 'undefined';
        },

        checkSelection() {
            this.allSelectedChecked = !this.items.some((item) => {
                return this.selection[item.id] === undefined;
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
        },
    },
});
