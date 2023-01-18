import template from './sw-text-editor-toolbar-table-button.html.twig';
import './sw-text-editor-toolbar-table-button.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-text-editor-toolbar-table-button', {
    template,

    props: {
        buttonConfig: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            addTableHead: false,
            tableRows: 6,
            tableCols: 6,
            tableMinCols: 6,
            tableMinRows: 6,
            rowMaxLimit: 10,
            colMaxLimit: 10,
            selectedRows: 0,
            selectedCols: 0,
            oldHorizontalDirection: '',
            oldVerticalDirection: '',
        };
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.$emit('mounted');
        },

        onMouseOverColumn(event, data) {
            if (!event.target) {
                return;
            }

            this.selectedRows = data.rows;
            this.selectedCols = data.cols;

            this.setSelectedTableColsAndRows();
        },

        setSelectedTableColsAndRows() {
            if (this.selectedRows <= 0 && this.selectedCols <= 0) {
                this.tableCols = 6;
                this.tableRows = 6;

                return;
            }

            const tbody = this.$el.querySelector('tbody');
            this.loopTableRows(tbody);
        },

        setSelectedCols(cols) {
            if (cols >= this.tableCols) {
                this.tableCols = cols;
            }

            this.$nextTick(() => {
                this.selectedCols = cols;

                this.setSelectedTableColsAndRows();
            });
        },

        setSelectedRows(rows) {
            if (rows >= this.tableRows) {
                this.tableRows = rows;
            }

            this.$nextTick(() => {
                this.selectedRows = rows;

                this.setSelectedTableColsAndRows();
            });
        },

        loopTableRows(tbody) {
            Object.values(tbody.children).forEach((child, index) => {
                this.loopTableCols(child, index);
            });
        },

        loopTableCols(row, rowIndex) {
            Object.values(row.children).forEach((child, index) => {
                if (index < this.selectedCols && rowIndex < this.selectedRows) {
                    child.classList.add('is--selected');
                } else {
                    child.classList.remove('is--selected');
                }
            });
        },

        onMouseOut(event) {
            if (!event.target) {
                return;
            }

            this.selectedRows = 0;
            this.selectedCols = 0;
        },

        onLastRowMouseOut(event) {
            if (!event) {
                return;
            }

            const verticalDirection = this.oldVerticalDirection < event.pageY ? 'down' : 'up';

            this.oldVerticalDirection = event.pageY;

            if (verticalDirection === 'down' && this.tableRows < this.rowMaxLimit) {
                this.tableRows += 1;
            } else if (verticalDirection === 'up' && this.tableRows > this.tableMinRows) {
                this.tableRows -= 1;
            }
        },

        onLastColMouseOut(event) {
            if (!event) {
                return;
            }

            const horizontalDirection = this.oldHorizontalDirection < event.pageX ? 'right' : 'left';

            this.oldHorizontalDirection = event.pageX;

            if (horizontalDirection === 'right' && this.tableCols < this.colMaxLimit) {
                this.tableCols += 1;
            } else if (horizontalDirection === 'left' && this.tableCols > this.tableMinCols) {
                this.tableCols -= 1;
            }
        },

        emitTable() {
            this.$emit('table-create', this.createHtmlTable());
        },

        createHtmlTable() {
            let tableHtml = '<table class="sw-text-editor-table">';
            let resizeHandle = '<div class="sw-text-editor-table__col-selector" contenteditable="false"></div>';

            if (this.addTableHead) {
                tableHtml += '<thead class="sw-text-editor-table__head"><tr class="sw-text-editor-table__row">';

                for (let i = 0; i < this.selectedCols; i += 1) {
                    tableHtml += `<td class="sw-text-editor-table__col">${resizeHandle}</td>`;
                }

                resizeHandle = '';
                tableHtml += '</tr></thead>';
            }

            tableHtml += '<tbody class="sw-text-editor-table__body">';

            for (let rows = 0; rows < this.selectedRows; rows += 1) {
                tableHtml += '<tr class="sw-text-editor-table__row">';

                for (let cols = 0; cols < this.selectedCols; cols += 1) {
                    tableHtml += `<td class="sw-text-editor-table__col">${resizeHandle}</td>`;
                }

                resizeHandle = '';
                tableHtml += '</tr>';
            }

            tableHtml += '</tbody></table>';
            // eslint-disable-next-line vue/no-mutating-props
            this.buttonConfig.value = tableHtml;
        },
    },
});
