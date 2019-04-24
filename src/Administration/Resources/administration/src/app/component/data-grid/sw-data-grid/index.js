import utils from 'src/core/service/util.service';
import template from './sw-data-grid.html.twig';
import './sw-data-grid.scss';

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
export default {
    name: 'sw-data-grid',

    template,

    props: {
        dataSource: {
            type: [Array, Object],
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
        }
    },

    data() {
        return {
            records: this.dataSource,
            currentSortBy: this.sortBy,
            currentSortDirection: this.sortDirection,
            loading: this.isLoading,
            currentColumns: [],
            columnIndex: null,
            selection: {},
            allSelectedChecked: false,
            originalTarget: null,
            compact: true,
            isInlineEditActive: false,
            currentInlineEditId: '',
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
                'sw-data-grid--actions': this.showActions
            };
        },

        localStorageItemKey() {
            return `${this.identifier}-grid-columns`;
        }
    },

    watch: {
        columns() {
            this.initGridColumns();
        },

        dataSource() {
            this.records = this.dataSource;
        },

        sortBy() {
            this.currentSortBy = this.sortBy;
        },

        sortDirection() {
            this.currentSortDirection = this.sortDirection;
        },

        isLoading() {
            this.loading = this.isLoading;
        }
    },

    methods: {
        createdComponent() {
            this.initGridColumns();
        },

        mountedComponent() {
            this.trackScrollX();

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

        getDefaultColumns() {
            return this.columns.map((column) => {
                const defaults = {
                    width: 'auto',
                    allowResize: false,
                    visible: true,
                    align: 'left'
                };

                if (!column.property) {
                    throw new Error(`[${this.$options.name}] Please specify a "property" to render a column.`);
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
                'sw-data-grid__cell--sortable': column.dataIndex
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
                `sw-data-grid__cell--align-${column.align}`
            ];
        },

        onChangeCompactMode(value) {
            this.compact = value;
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
            this.isInlineEditActive = true;
            this.setAllColumnElementWidths();
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
            const accessor = column.property.split('.');
            accessor.splice(accessor.length - 1, 0, 'translated');

            const translated = utils.get(item, accessor);
            if (translated) {
                return translated;
            }
            return utils.get(item, column.property);
        },

        selectAll(selected) {
            this.$delete(this.selection);

            this.records.forEach((item) => {
                if (this.isSelected(item.id) !== selected) {
                    this.selectItem(selected, item);
                }
            });

            this.allSelectedChecked = selected;
            this.$emit('select-all-items', this.selection);
        },

        selectItem(selected, item) {
            const selection = this.selection;

            if (selected === true) {
                selection[item.id] = item;
            } else if (!selected && selection[item.id]) {
                delete this.selection[item.id];
            }

            this.$delete(this.selection);
            this.$set(this.selection);

            this.checkSelection();
            this.$emit('select-item', this.selection, item, selected);
        },

        isSelected(itemId) {
            return typeof this.selection[itemId] !== 'undefined';
        },

        checkSelection() {
            let selected = true;
            this.records.forEach((item) => {
                if (this.selection[item.id] === undefined) {
                    selected = false;
                }
            });
            this.allSelectedChecked = selected;
        },

        onClickSaveInlineEdit(item) {
            this.$emit('inline-edit-assign');
            this.save(item);

            this.disableInlineEdit();
        },

        onClickCancelInlineEdit(item) {
            this.$emit('inline-edit-assign');
            this.revert(item);

            this.disableInlineEdit();
        },

        onDbClickCell(record) {
            if (!this.allowInlineEdit) {
                return;
            }

            this.enableInlineEdit();
            this.currentInlineEditId = record.id;
        },

        onClickHeaderCell(event, column) {
            if (this._isResizing) {
                return;
            }

            if (!column.dataIndex) {
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
};
