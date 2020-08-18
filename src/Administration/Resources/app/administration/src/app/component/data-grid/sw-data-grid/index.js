import template from './sw-data-grid.html.twig';
import './sw-data-grid.scss';

const { Component } = Shopware;
const utils = Shopware.Utils;

/**
 * @public
 * @status ready
 * @description The sw-data-grid is a component to render tables with data.
 * It also supports hiding columns or scrolling horizontally when many columns are present.
 * @example-type static
 * @component-example
 * <sw-data-grid
 *     :dataSource="[
 *         { id: 'uuid1', company: 'Wordify', name: 'Portia Jobson' },
 *         { id: 'uuid2', company: 'Twitternation', name: 'Baxy Eardley' },
 *         { id: 'uuid3', company: 'Skidoo', name: 'Arturo Staker' },
 *         { id: 'uuid4', company: 'Meetz', name: 'Dalston Top' },
 *         { id: 'uuid5', company: 'Photojam', name: 'Neddy Jensen' }
 *     ]"
 *     :columns="[
 *          { property: 'name', label: 'Name', rawData: true },
 *          { property: 'company', label: 'Company', rawData: true }
 *     ]">
 * </sw-data-grid>
 */
Component.register('sw-data-grid', {
    template,

    props: {
        dataSource: {
            type: Array,
            required: true
        },

        columns: {
            type: Array,
            required: true
        },

        identifier: {
            type: String,
            required: false,
            default: ''
        },

        showSelection: {
            type: Boolean,
            default: true,
            required: false
        },

        showActions: {
            type: Boolean,
            default: true,
            required: false
        },

        showHeader: {
            type: Boolean,
            default: true,
            required: false
        },

        showSettings: {
            type: Boolean,
            default: false,
            required: false
        },

        fullPage: {
            type: Boolean,
            default: false,
            required: false
        },

        allowInlineEdit: {
            type: Boolean,
            default: false,
            required: false
        },

        allowColumnEdit: {
            type: Boolean,
            default: false,
            required: false
        },

        isLoading: {
            type: Boolean,
            default: false,
            required: false
        },

        skeletonItemAmount: {
            type: Number,
            required: false,
            default: 7
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

        naturalSorting: {
            type: Boolean,
            required: false,
            default: false
        },

        compactMode: {
            type: Boolean,
            required: false,
            default: true
        },

        plainAppearance: {
            type: Boolean,
            required: false,
            default: false
        },

        showPreviews: {
            type: Boolean,
            required: false,
            default: true
        },

        isRecordEditable: {
            type: Function,
            required: false,
            default() {
                return true;
            }
        },

        isRecordSelectable: {
            type: Function,
            required: false,
            default() {
                return true;
            }
        }
    },

    data() {
        return {
            /** @type {Array} */
            records: this.dataSource,
            currentSortBy: this.sortBy,
            currentSortDirection: this.sortDirection,
            currentNaturalSorting: this.naturalSorting,
            loading: this.isLoading,
            currentColumns: [],
            columnIndex: null,
            selection: {},
            originalTarget: null,
            compact: this.compactMode,
            previews: this.showPreviews,
            isInlineEditActive: false,
            currentInlineEditId: '',
            hasPreviewSlots: false,
            hasResizeColumns: false,
            _hasColumnsResize: false,
            _isResizing: false
        };
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    computed: {
        classes() {
            return {
                'is--compact': this.compact,
                'sw-data-grid--full-page': this.fullPage,
                'sw-data-grid--actions': this.showActions,
                'sw-data-grid--plain-appearance': this.plainAppearance
            };
        },

        localStorageItemKey() {
            return `${this.identifier}-grid-columns`;
        },

        selectionCount() {
            return Object.values(this.selection).length;
        },

        allSelectedChecked() {
            if (!this.records || this.records.length === 0) {
                return false;
            }

            if (this.selectionCount < this.records.length) {
                return false;
            }

            const selectedItems = Object.values(this.selection);
            return this.records.reduce((acc, item) => {
                if (!selectedItems.some((selection) => selection === item)) {
                    acc = false;
                }

                return acc;
            }, true);
        }
    },

    watch: {
        columns() {
            this.initGridColumns();
        },

        sortBy() {
            this.currentSortBy = this.sortBy;
        },

        sortDirection() {
            this.currentSortDirection = this.sortDirection;
        },

        naturalSorting() {
            this.currentNaturalSorting = this.naturalSorting;
        },

        isLoading() {
            this.loading = this.isLoading;
        },

        dataSource() {
            this.records = this.dataSource;
        },

        showSelection() {
            this.selection = this.showSelection ? this.selection : {};
        },

        records() {
            this.selection = {};
        },

        compactMode() {
            this.compact = this.compactMode;
        },

        selection() {
            this.$emit('selection-change', this.selection, this.selectionCount);
        }
    },

    methods: {
        createdComponent() {
            this.initGridColumns();
        },

        mountedComponent() {
            this.trackScrollX();
            this.findPreviewSlots();

            this.$device.onResize({
                listener: this.trackScrollX.bind(this),
                component: this
            });
        },

        initGridColumns() {
            let columns = this.getDefaultColumns();

            if (this.identifier) {
                const storageItem = window.localStorage.getItem(this.localStorageItemKey);

                if (storageItem !== null) {
                    columns = JSON.parse(storageItem);
                }
            }

            this.currentColumns = columns;

            this.findResizeColumns();
        },

        findResizeColumns() {
            this.hasResizeColumns = this.currentColumns.some((column) => {
                return column.allowResize;
            });
        },

        findPreviewSlots() {
            const scopedSlots = Array.from(Object.keys(this.$scopedSlots));

            this.hasPreviewSlots = scopedSlots.some((scopedSlot) => {
                return scopedSlot.includes('preview-');
            });
        },

        getDefaultColumns() {
            return this.columns.map((column) => {
                const defaults = {
                    width: 'auto',
                    allowResize: false,
                    sortable: true,
                    visible: true,
                    align: 'left'
                };

                if (!column.property) {
                    throw new Error(`[${this.$options.name}] Please specify a "property" to render a column.`);
                }
                if (!column.dataIndex) {
                    column.dataIndex = column.property;
                }

                return Object.assign({}, defaults, column);
            });
        },

        saveGridColumns() {
            if (!this.identifier) {
                return;
            }
            window.localStorage.setItem(this.localStorageItemKey, JSON.stringify(this.currentColumns));
        },

        getHeaderCellClasses(column, index) {
            return [{
                'sw-data-grid__cell--sortable': column.dataIndex,
                'sw-data-grid__cell--icon-label': column.iconLabel
            },
            `sw-data-grid__cell--${index}`
            ];
        },

        getRowClasses(item, itemIndex) {
            return [
                {
                    'is--inline-edit': this.isInlineEdit(item),
                    'is--selected': this.isSelected(item.id)
                },
                `sw-data-grid__row--${itemIndex}`
            ];
        },

        getCellClasses(column) {
            return [
                `sw-data-grid__cell--${column.property.replace(/\./g, '-')}`,
                `sw-data-grid__cell--align-${column.align}`,
                {
                    'sw-data-grid__cell--multi-line': column.multiLine
                }
            ];
        },

        onChangeCompactMode(value) {
            this.compact = value;
        },

        onChangePreviews(value) {
            this.previews = value;
        },

        onChangeColumnVisibility(value, index) {
            this.currentColumns[index].visible = value;
            this.saveGridColumns();
        },

        onChangeColumnOrder(currentColumnIndex, newColumnIndex) {
            this.currentColumns = this.orderColumns(this.currentColumns, currentColumnIndex, newColumnIndex);
            this.saveGridColumns();
        },

        orderColumns(columns, oldColumnIndex, newColumnIndex) {
            columns.splice(newColumnIndex, 0, columns.splice(oldColumnIndex, 1)[0]);

            return columns;
        },

        enableInlineEdit() {
            this.isInlineEditActive = this.hasColumnWithInlineEdit();
            this.setAllColumnElementWidths();
        },

        hasColumnWithInlineEdit() {
            return this.currentColumns.some((item) => {
                return item.hasOwnProperty('inlineEdit');
            });
        },

        isInlineEdit(item) {
            return this.isInlineEditActive && this.currentInlineEditId === item.id;
        },

        disableInlineEdit() {
            this.isInlineEditActive = false;
            this.currentInlineEditId = '';
        },

        hideColumn(columnIndex) {
            this.currentColumns[columnIndex].visible = false;
            this.saveGridColumns();
        },

        renderColumn(item, column) {
            let accessor = column.property.split('.');
            let workingProperty = column.property;

            if (accessor.lastIndexOf('last()') !== -1) {
                item = utils.get(item, accessor.splice(0, accessor.lastIndexOf('last()'))).last();
                accessor = accessor.splice(accessor.lastIndexOf('last()') + 1, accessor.length - 1);
                workingProperty = accessor.join('.');
            }
            accessor.splice(accessor.length - 1, 0, 'translated');
            const translated = utils.get(item, accessor);
            if (translated) {
                return translated;
            }
            return utils.get(item, workingProperty);
        },

        selectAll(selected) {
            this.$delete(this.selection);

            this.records.forEach((item) => {
                if (this.isSelected(item.id) !== selected) {
                    this.selectItem(selected, item);
                }
            });

            this.$emit('select-all-items', this.selection);
        },

        selectItem(selected, item) {
            if (!this.isRecordSelectable(item)) {
                return;
            }

            const selection = this.selection;

            if (selected === true) {
                this.$set(this.selection, item.id, item);
            } else if (!selected && selection[item.id]) {
                this.$delete(this.selection, item.id);
            }

            this.$emit('select-item', this.selection, item, selected);
        },

        isSelected(itemId) {
            return typeof this.selection[itemId] !== 'undefined';
        },

        resetSelection() {
            this.selection = {};
        },

        onClickSaveInlineEdit(item) {
            this.$emit('inline-edit-assign');
            this.save(item);

            this.disableInlineEdit();
        },

        onClickCancelInlineEdit(item) {
            this.revert(item);

            this.disableInlineEdit();
        },

        onDbClickCell(record) {
            if (!this.allowInlineEdit || !this.isRecordEditable(record)) {
                return;
            }

            this.enableInlineEdit();
            this.currentInlineEditId = record.id;
        },

        onClickHeaderCell(event, column) {
            if (this._isResizing) {
                return;
            }

            if (!column.sortable) {
                return;
            }

            if (event.target.closest('.sw-context-button') ||
                event.target.closest('.sw-data-grid__cell-resize')) {
                return;
            }

            this.setAllColumnElementWidths();

            this.sort(column);
        },

        onStartResize(event, column, columnIndex) {
            this.resizeX = event.pageX;
            this.originalTarget = event.target;
            this.columnIndex = columnIndex;
            this._isResizing = true;

            this._handleColumnResizeClasses('add');

            this.enableResizeMode();

            window.addEventListener('mousemove', this.onResize, false);
            window.addEventListener('mouseup', this.onStopResize, false);
        },

        onStopResize() {
            this.resizeX = null;

            this._handleColumnResizeClasses('remove');

            this.currentColumns[this.columnIndex].width = `${this._currentColumnWidth}px`;

            this._currentColumnWidth = null;
            this.originalTarget = null;
            this.columnIndex = null;

            utils.debounce(() => {
                this._isResizing = false;
            }, 50)();

            window.removeEventListener('mouseup', this.onStopResize, false);
            window.removeEventListener('mousemove', this.onResize, false);
        },

        onResize(event) {
            if (this.resizeX === null) {
                return;
            }

            const currentColumnElement = this.originalTarget.parentNode;
            const pageX = event.pageX;
            const diffX = pageX - this.resizeX;
            const newColumnWidth = currentColumnElement.offsetWidth + diffX;

            this.resizeX = pageX;
            this.trackScrollX();

            if (newColumnWidth < 65) {
                return;
            }

            currentColumnElement.style.width = `${newColumnWidth}px`;
            currentColumnElement.style.minWidth = `${newColumnWidth}px`;

            this._currentColumnWidth = newColumnWidth;
        },

        _handleColumnResizeClasses(operation) {
            const resizeElement = this.originalTarget;
            const columnElement = resizeElement.parentNode;

            this.$el.classList[operation]('is--resizing');
            resizeElement.classList[operation]('is--column-resizing');
            columnElement.classList[operation]('is--column-resizing');
            columnElement.nextElementSibling.classList[operation]('is--column-resizing');
        },

        enableResizeMode() {
            if (this._hasColumnsResize) {
                return;
            }

            this.setAllColumnElementWidths();

            this.$refs.table.style.tableLayout = 'fixed';
            this._hasColumnsResize = true;
        },

        setAllColumnElementWidths() {
            this.$refs.column.forEach((element) => {
                const currentWidth = `${element.offsetWidth}px`;

                if (element.offsetWidth) {
                    element.style.width = currentWidth;
                    element.style.minWidth = currentWidth;
                }
            });
        },

        trackScrollX() {
            const el = this.$el;
            const wrapperEl = this.$refs.wrapper;

            if (!wrapperEl) {
                return;
            }

            if (wrapperEl.clientWidth < wrapperEl.scrollWidth) {
                el.classList.add('is--scroll-x');
            } else {
                el.classList.remove('is--scroll-x');
            }
        },

        save(item) {
            this.$emit('inline-edit-save', item);
        },

        revert(item) {
            this.$emit('inline-edit-cancel', item);
        },

        sort(column) {
            this.$emit('column-sort', column);
        }
    }
});
