import utils from 'src/core/service/util.service';
import template from './sw-data-grid.html.twig';
import './sw-data-grid.scss';

export default {
    name: 'sw-data-grid',

    template,

    props: {
        dataSource: {
            type: [Array, Object],
            required: true,
            default() {
                return [];
            }
        },

        columns: {
            type: Array,
            required: true,
            default() {
                return [];
            }
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
            currentColumns: [],
            columnIndex: null,
            selection: {},
            allSelectedChecked: false,
            originalTarget: null,
            compact: true,
            isInlineEditActive: false,
            currentInlineEditId: '',
            hasResizeColumns: false,
            _columnsResize: false,
            _currentColumnWidth: null
        };
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    computed: {
        classes() {
            return {
                'is--compact': this.compact,
                'sw-data-grid--full-page': this.fullPage
            };
        },

        localStorageItemKey() {
            return `${this.identifier}-grid-columns`;
        }
    },

    methods: {
        createdComponent() {
            this.initGridColumns();
        },

        mountedComponent() {
            this.trackScrollX();

            window.addEventListener('resize', this.trackScrollX.bind(this));
        },

        destroyedComponent() {
            window.removeEventListener('resize', this.trackScrollX.bind(this));
        },

        initGridColumns() {
            const storageItem = window.localStorage.getItem(this.localStorageItemKey);

            if (this.identifier && storageItem !== null) {
                this.currentColumns = JSON.parse(storageItem);
            } else {
                this.currentColumns = this.getDefaultColumns();
            }

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
            window.localStorage.setItem(this.localStorageItemKey, JSON.stringify(this.currentColumns));
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
            if (column.rawData) {
                return utils.get(item, column.property);
            }
            return utils.get(item, `meta.viewData.${column.property}`);
        },

        selectAll(selected) {
            this.$delete(this.selection);

            this.dataSource.forEach((item) => {
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
            this.allSelectedChecked = !this.dataSource.some((item) => {
                return this.selection[item.id] === undefined;
            });
        },

        onClickSaveInlineEdit(item) {
            this.$emit('inline-edit-assign');
            this.$emit('inline-edit-save', item);

            this.disableInlineEdit();
        },

        onClickCancelInlineEdit(item) {
            this.$emit('inline-edit-assign');
            this.$emit('inline-edit-cancel', item);

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
            if (!column.dataIndex) {
                return;
            }

            if (event.target.closest('.sw-context-button') ||
                event.target.closest('.sw-data-grid__cell-resize')) {
                return;
            }

            this.setAllColumnElementWidths();

            this.$emit('column-sort', column);
        },

        onStartResize(event, column, columnIndex) {
            this.resizeX = event.pageX;
            this.originalTarget = event.target;
            this.columnIndex = columnIndex;

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
            currentColumnElement.style.width = `${newColumnWidth}px`;
            currentColumnElement.style.minWidth = `${newColumnWidth}px`;

            this.trackScrollX();

            this._currentColumnWidth = newColumnWidth;
            this.resizeX = pageX;
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
            if (this._columnsResize) {
                return;
            }

            this.setAllColumnElementWidths();

            this.$refs.table.style.tableLayout = 'fixed';
            this._columnsResize = true;
        },

        setAllColumnElementWidths(auto = false) {
            this.$refs.column.forEach((element) => {
                const currentWidth = element.offsetWidth;

                if (element.offsetWidth) {
                    element.style.width = auto ? 'auto' : `${currentWidth}px`;
                    element.style.minWidth = auto ? 'auto' : `${currentWidth}px`;
                }
            });
        },

        trackScrollX: utils.debounce(function debouncedResize() {
            const el = this.$el;
            const wrapperEl = this.$refs.wrapper;

            if (wrapperEl.clientWidth < wrapperEl.scrollWidth) {
                el.classList.add('is--scroll-x');
            } else {
                el.classList.remove('is--scroll-x');
            }
        }, 100)
    }
};
