import template from './sw-grid.html.twig';
import './sw-grid.scss';

const { Component } = Shopware;
const { dom } = Shopware.Utils;

/**
 * @package admin
 *
 * @private
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
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-grid', {
    template,

    compatConfig: Shopware.compatConfig,

    provide() {
        return {
            swGridInlineEditStart: this.inlineEditingStart,
            swGridInlineEditCancel: this.disableActiveInlineEditing,
            swOnInlineEditStart: this.onInlineEditStart,
            swRegisterGridDisableInlineEditListener: this.registerGridDisableInlineEditListener,
            swUnregisterGridDisableInlineEditListener: this.unregisterGridDisableInlineEditListener,
            swGridSetColumns: this.setColumns,
            swGridColumns: this.columns,
        };
    },

    emits: [
        'inline-edit-finish',
        'inline-edit-start',
        'sw-grid-disable-inline-editing',
        'inline-edit-cancel',
        'sw-grid-select-all',
        'sw-grid-select-item',
        'sort-column',
    ],

    props: {
        items: {
            type: Array,
            required: false,
            default: null,
        },

        selectable: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
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
            // eslint-disable-next-line vue/no-boolean-default
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
            // eslint-disable-next-line vue/no-boolean-default
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
            swGridDisableInlineEditListener: [],
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

        registerGridDisableInlineEditListener(listener) {
            this.swGridDisableInlineEditListener.push(listener);
        },

        unregisterGridDisableInlineEditListener(listener) {
            this.swGridDisableInlineEditListener = this.swGridDisableInlineEditListener.filter((l) => l !== listener);
        },

        onInlineEditFinish(item) {
            this.editing = null;
            this.$emit('inline-edit-finish', item);
        },

        onInlineEditStart(item) {
            this.$emit('inline-edit-start', item);
        },

        registerInlineEditingEvents() {
            // New way is using the provide/inject
            if (this.isCompatEnabled('INSTANCE_EVENT_EMITTER')) {
                // eslint-disable-next-line vue/no-deprecated-events-api
                this.$on('sw-row-inline-edit-start', this.inlineEditingStart);
                // eslint-disable-next-line vue/no-deprecated-events-api
                this.$on('sw-row-inline-edit-cancel', this.disableActiveInlineEditing);
            }
        },

        inlineEditingStart(id) {
            if (this.editing != null) {
                this.$emit('sw-grid-disable-inline-editing', this.editing);
            }

            this.editing = id;
        },

        disableActiveInlineEditing(item, index) {
            this.editing = null;
            this.$emit('inline-edit-cancel', item, index);
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

        /**
         * @deprecated tag:v6.7.0 - isGridDisabled function will be removed.
         */
        isGridDisabled(itemId) {
            return this.isSelected(itemId) && this.selection[itemId].gridDisabled;
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

        setColumns(columns) {
            this.columns = columns;
        },

        getKey(item) {
            if (item.id === undefined || item.id === null) {
                // see https://vuejs.org/api/built-in-special-attributes.html#key
                // we use child components with state
                // (at least sw-grid-row, maybe even form elements, depending on the slot usage)
                // means not having a proper unique identifier for each row likely causes issues.
                // For example the child components may not be properly destroyed and created and just
                // "patched" in place with a completely different item / row
                Shopware.Utils.debug.error(
                    'sw-grid item without `id` property',
                    item,
                    'more info here: https://vuejs.org/api/built-in-special-attributes.html#key',
                );
                return undefined;
            }

            return item.id;
        },
    },
});
