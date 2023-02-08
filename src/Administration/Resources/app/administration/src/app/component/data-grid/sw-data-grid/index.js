import template from './sw-data-grid.html.twig';
import './sw-data-grid.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const utils = Shopware.Utils;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
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
 *          { property: 'name', label: 'Name' },
 *          { property: 'company', label: 'Company' }
 *     ]">
 * </sw-data-grid>
 */
Component.register('sw-data-grid', {
    template,

    inject: [
        'acl',
        'repositoryFactory',
    ],

    props: {
        dataSource: {
            type: Array,
            required: true,
        },

        columns: {
            type: Array,
            required: true,
        },

        identifier: {
            type: String,
            required: false,
            default: '',
        },

        showSelection: {
            type: Boolean,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
            required: false,
        },

        showActions: {
            type: Boolean,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
            required: false,
        },

        showHeader: {
            type: Boolean,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
            required: false,
        },

        showSettings: {
            type: Boolean,
            default: false,
            required: false,
        },

        fullPage: {
            type: Boolean,
            default: false,
            required: false,
        },

        allowInlineEdit: {
            type: Boolean,
            default: false,
            required: false,
        },

        allowColumnEdit: {
            type: Boolean,
            default: false,
            required: false,
        },

        isLoading: {
            type: Boolean,
            default: false,
            required: false,
        },

        skeletonItemAmount: {
            type: Number,
            required: false,
            default: 7,
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

        naturalSorting: {
            type: Boolean,
            required: false,
            default: false,
        },

        compactMode: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        plainAppearance: {
            type: Boolean,
            required: false,
            default: false,
        },

        showPreviews: {
            type: Boolean,
            required: false,
            // TODO: Boolean props should only be opt in and therefore default to false
            // eslint-disable-next-line vue/no-boolean-default
            default: true,
        },

        isRecordEditable: {
            type: Function,
            required: false,
            default() {
                return true;
            },
        },

        isRecordSelectable: {
            type: Function,
            required: false,
            default(item) {
                return !this.reachMaximumSelectionExceed ||
                    Object.keys(this.selection).includes(item[this.itemIdentifierProperty]);
            },
        },

        itemIdentifierProperty: {
            type: String,
            required: false,
            default: 'id',
        },

        maximumSelectItems: {
            type: Number,
            required: false,
            default: null,
        },

        preSelection: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            /** @type {Array} */
            records: this.dataSource,
            currentSortBy: this.sortBy,
            currentSortDirection: this.sortDirection,
            currentNaturalSorting: this.naturalSorting,
            loading: this.isLoading,
            currentSetting: {},
            currentColumns: [],
            columnIndex: null,
            selection: { ...this.preSelection || {} },
            originalTarget: null,
            compact: this.compactMode,
            previews: this.showPreviews,
            isInlineEditActive: false,
            currentInlineEditId: '',
            hasPreviewSlots: false,
            hasResizeColumns: false,
            // eslint-disable-next-line vue/no-reserved-keys
            _hasColumnsResize: false,
            // eslint-disable-next-line vue/no-reserved-keys
            _isResizing: false,
        };
    },

    computed: {
        classes() {
            return {
                'is--compact': this.compact,
                'sw-data-grid--full-page': this.fullPage,
                'sw-data-grid--actions': this.showActions,
                'sw-data-grid--plain-appearance': this.plainAppearance,
            };
        },

        selectionCount() {
            return Object.values(this.selection).length;
        },

        reachMaximumSelectionExceed() {
            if (!this.maximumSelectItems) {
                return false;
            }

            return this.selectionCount >= this.maximumSelectItems;
        },

        isSelectAllDisabled() {
            if (!this.maximumSelectItems) {
                return false;
            }

            if (!this.records) {
                return false;
            }

            const currentVisibleIds = this.records.map(record => record.id);

            return this.reachMaximumSelectionExceed
                && Object.keys(this.selection).every(id => !currentVisibleIds.includes(id));
        },

        allSelectedChecked() {
            if (this.isSelectAllDisabled) {
                return false;
            }

            if (this.reachMaximumSelectionExceed) {
                return true;
            }

            if (!this.records || this.records.length === 0) {
                return false;
            }

            if (this.selectionCount < this.records.length) {
                return false;
            }

            const selectedItems = Object.values(this.selection);

            return this.records.every(item => {
                return selectedItems.some((selection) => {
                    return selection[this.itemIdentifierProperty] === item[this.itemIdentifierProperty];
                });
            });
        },

        userConfigRepository() {
            return this.repositoryFactory.create('user_config');
        },

        currentUser() {
            return Shopware.State.get('session').currentUser;
        },

        userGridSettingCriteria() {
            const criteria = new Criteria(1, 25);
            const configurationKey = `grid.setting.${this.identifier}`;
            criteria.addFilter(Criteria.equals('key', configurationKey));
            criteria.addFilter(Criteria.equals('userId', this.currentUser && this.currentUser.id));

            return criteria;
        },

        hasInvisibleSelection() {
            if (!this.records) {
                return false;
            }

            const currentVisibleIds = this.records.map(record => record.id);
            return this.selectionCount > 0 && Object.keys(this.selection).some(id => !currentVisibleIds.includes(id));
        },
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

        compactMode() {
            this.compact = this.compactMode;
        },

        selection() {
            this.$emit('selection-change', this.selection, this.selectionCount);
        },
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
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
                component: this,
            });
        },

        initGridColumns() {
            this.currentColumns = this.getDefaultColumns();
            this.findResizeColumns();

            if (!this.identifier) {
                return;
            }

            this.findUserSetting();
        },

        findUserSetting() {
            if (!this.acl.can('user_config:read')) {
                return Promise.resolve();
            }

            return this.userConfigRepository.search(
                this.userGridSettingCriteria,
                Shopware.Context.api,
            ).then((response) => {
                if (!response.length) {
                    return;
                }

                this.currentSetting = response[0];
                const userSetting = response[0].value;

                this.applyUserSettings({
                    columns: userSetting?.columns ?? userSetting,
                    compact: userSetting?.compact,
                    previews: userSetting?.previews,
                });
            });
        },

        findUserSettingById() {
            return this.userConfigRepository.get(this.currentSetting.id, Shopware.Context.api).then((response) => {
                if (!response) {
                    return;
                }

                this.currentSetting = response;
                const userSetting = response.value;

                this.applyUserSettings({
                    columns: userSetting?.columns ?? userSetting,
                    compact: userSetting?.compact,
                    previews: userSetting?.previews,
                });
            });
        },

        applyUserSettings(userSettings) {
            if (typeof userSettings.compact === 'boolean') {
                this.compact = userSettings.compact;
            }

            if (typeof userSettings.previews === 'boolean') {
                this.previews = userSettings.previews;
            }

            if (!userSettings.columns) {
                return;
            }

            const userColumnSettings = Object.fromEntries(userSettings.columns.map((column, index) => {
                return [
                    column.dataIndex, {
                        width: column.width,
                        allowResize: column.allowResize,
                        sortable: column.sortable,
                        visible: column.visible,
                        align: column.align,
                        naturalSorting: column.naturalSorting,
                        position: index,
                    },
                ];
            }));

            this.currentColumns = this.currentColumns.map(column => {
                if (userColumnSettings[column.dataIndex] === undefined) {
                    return column;
                }

                return utils.object.mergeWith(
                    {},
                    column,
                    userColumnSettings[column.dataIndex],
                    (localValue, serverValue) => {
                        if (serverValue !== undefined && serverValue !== null) {
                            return serverValue;
                        }

                        return localValue;
                    },
                );
            }).sort((column1, column2) => column1.position - column2.position);
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
                    align: 'left',
                    naturalSorting: false,
                };

                if (!column.property) {
                    throw new Error(`[${this.$options.name}] Please specify a "property" to render a column.`);
                }
                if (!column.dataIndex) {
                    column.dataIndex = column.property;
                }

                return { ...defaults, ...column };
            });
        },

        createUserGridSetting() {
            const newUserGrid = this.userConfigRepository.create(Shopware.Context.api);
            newUserGrid.key = `grid.setting.${this.identifier}`;
            newUserGrid.userId = this.currentUser && this.currentUser.id;
            this.currentSetting = newUserGrid;
        },

        saveUserSettings() {
            if (!this.acl.can('user_config:create') || !this.acl.can('user_config:update')) {
                return;
            }

            if (!this.identifier) {
                return;
            }

            if (!this.currentSetting.id) {
                this.createUserGridSetting();
            }

            this.currentSetting.value = {
                columns: this.currentColumns,
                compact: this.compact,
                previews: this.previews,
            };
            this.userConfigRepository.save(this.currentSetting, Shopware.Context.api).then(() => {
                this.findUserSettingById();
            });
        },

        getHeaderCellClasses(column, index) {
            return [
                {
                    'sw-data-grid__cell--sortable': column.sortable,
                    'sw-data-grid__cell--icon-label': column.iconLabel,
                },
                `sw-data-grid__cell--${index}`,
                `sw-data-grid__cell--align-${column.align}`,
            ];
        },

        getRowClasses(item, itemIndex) {
            return [
                {
                    'is--inline-edit': this.isInlineEdit(item),
                    'is--selected': this.isSelected(item.id),
                },
                `sw-data-grid__row--${itemIndex}`,
            ];
        },

        getCellClasses(column) {
            return [
                `sw-data-grid__cell--${column.property.replace(/\./g, '-')}`,
                `sw-data-grid__cell--align-${column.align}`,
                {
                    'sw-data-grid__cell--multi-line': column.multiLine,
                },
            ];
        },

        onChangeCompactMode(value) {
            this.compact = value;
            this.saveUserSettings();
        },

        onChangePreviews(value) {
            this.previews = value;
            this.saveUserSettings();
        },

        onChangeColumnVisibility(value, index) {
            this.currentColumns[index].visible = value;

            this.saveUserSettings();
        },

        onChangeColumnOrder(currentColumnIndex, newColumnIndex) {
            this.currentColumns = this.orderColumns(this.currentColumns, currentColumnIndex, newColumnIndex);

            this.saveUserSettings();
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
            return this.isInlineEditActive && this.currentInlineEditId === item[this.itemIdentifierProperty];
        },

        disableInlineEdit() {
            this.isInlineEditActive = false;
            this.currentInlineEditId = '';
        },

        hideColumn(columnIndex) {
            this.currentColumns[columnIndex].visible = false;

            this.saveUserSettings();
        },

        renderColumn(item, column) {
            // horror (pseudo) example: deliveries[0].stateMachineState.transactions.last().name
            // (name is a translated field - developer forgot translated accessor)
            // pointer is now the order
            const accessor = column.property.split('.');
            let pointer = item;

            // parts:  [`deliveries[0]`, `type`, `name`]
            accessor.forEach((part) => {
                // #1 loop: part=deliveries[0]      pointer=order object
                // #2 loop: part=stateMachineState  pointer=delivery object
                // #3 loop: part=transactions       pointer=stateMachineState
                // #4 loop: part=last()             pointer=transactions
                // #5 loop: part=name               pointer=last entity in transaction collection

                if (typeof pointer !== 'object' || pointer === null) {
                    utils.debug.warn(`[sw-data-grid] Can not resolve accessor: ${column.property}`);
                    return false;
                }

                // check if the current accessor part is a function call like e.g. entity collection "last()"
                if (part.includes('()')) {
                    part = part.replace('()', '');
                }

                if (typeof pointer[part] === 'function') {
                    pointer = pointer[part]();
                } else if (pointer.hasOwnProperty('translated') && pointer.translated.hasOwnProperty(part)) {
                    pointer = pointer.translated[part];
                } else {
                    // resolve dynamic accessor part: (name, deliveries[0], translated)
                    pointer = utils.get(pointer, part);
                }

                return true;
            });

            return pointer;
        },

        selectAll(selected) {
            this.$delete(this.selection);

            this.records.forEach(item => {
                if (this.isSelected(item[this.itemIdentifierProperty]) !== selected) {
                    this.selectItem(selected, item);
                }
            });

            this.$emit('select-all-items', this.selection);
        },

        selectItem(selected, item) {
            if (selected && this.reachMaximumSelectionExceed) {
                return;
            }

            if (!this.isRecordSelectable(item)) {
                return;
            }

            const selection = this.selection;

            if (selected) {
                this.$set(this.selection, item[this.itemIdentifierProperty], item);
            } else if (!selected && selection[item[this.itemIdentifierProperty]]) {
                this.$delete(this.selection, item[this.itemIdentifierProperty]);
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
            this.$emit('inline-edit-assign', item);
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
            this.currentInlineEditId = record[this.itemIdentifierProperty];
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

            Shopware.Utils.debounce(() => {
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
        },
    },
});
