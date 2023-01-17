import template from './sw-text-editor-table-toolbar.html.twig';
import './sw-text-editor-table-toolbar.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 */
Component.register('sw-text-editor-table-toolbar', {
    template,

    props: {
        // FIXME: add property type
        // eslint-disable-next-line vue/require-prop-types
        selection: {
            required: false,
            default: null,
        },
    },

    data() {
        return {
            range: null,
            colClassName: 'sw-text-editor-table__col',
            resizeHandle: '<div class="sw-text-editor-table__col-selector" contenteditable="false"></div>',
        };
    },

    methods: {
        onAddRow(position) {
            const { table, index } = this.getVariables('tr');

            if (!table || index === undefined) {
                return;
            }

            const newRowIdx = position === 'after' ? index + 1 : index;

            const newFirstRow = newRowIdx === 0;
            if (newFirstRow) {
                this.removeResizeHandle(table);
            }

            const colCount = table.rows[0].cells.length;
            const newRow = table.insertRow(newRowIdx);
            newRow.className = 'sw-text-editor-table__row';

            this.fillRowWithCells(newRow, colCount, newFirstRow);

            this.$emit('table-modify', table);
        },

        fillRowWithCells(row, colCount, newFirstRow) {
            let newCell;

            for (let i = 0; i < colCount; i += 1) {
                newCell = row.insertCell();
                newCell.className = this.colClassName;

                if (newFirstRow) {
                    newCell.innerHTML = this.resizeHandle;
                }
            }
        },

        removeResizeHandle(table) {
            const handles = table.querySelectorAll('.sw-text-editor-table__col-selector');

            Object.values(handles).forEach((handle) => {
                handle.remove();
            });
        },

        onAddColumn(position) {
            const { table, index } = this.getVariables('td');

            if (!table || index === undefined) {
                return;
            }

            const tBodies = Object.values(table.tBodies);
            const thead = table.tHead;

            const newIndex = position === 'after' ? index + 1 : index;

            if (thead) {
                this.insertNewCellsForColumn(Object.values(thead.children), newIndex, true);
            }

            tBodies.forEach((tbody) => {
                this.insertNewCellsForColumn(Object.values(tbody.children), newIndex, !thead);
            });

            this.$emit('table-modify', table);

            this.keepSelection();
        },

        insertNewCellsForColumn(rows, colIdx, addResizeHanlde = false) {
            let newCell;

            rows.forEach((row, index) => {
                newCell = row.insertCell(colIdx);
                newCell.className = this.colClassName;

                if (index === 0 && addResizeHanlde) {
                    newCell.innerHTML = this.resizeHandle;
                }
            });
        },

        onDeleteColumn() {
            const { table, index } = this.getVariables('td');

            if (!table || index === undefined) {
                return;
            }

            const tBodies = Object.values(table.tBodies);
            const thead = table.tHead;

            if (!tBodies) {
                return;
            }

            if (thead) {
                this.deleteCells(Object.values(thead.children), index);
            }

            tBodies.forEach((tbody) => {
                this.deleteCells(Object.values(tbody.children), index);
            });

            this.setRangeAfterDelete(table, index, 'cell');
            this.keepSelection();
        },

        setRangeAfterDelete(table, index, mode) {
            if (this.selection.rangeCount > 0) {
                this.selection.removeAllRanges();
            }

            const range = new Range();
            let rangeRow;
            let rangeTd;

            if (mode === 'cell') {
                rangeRow = table.rows[0];

                if (!rangeRow) return;

                rangeTd = !rangeRow.children[index] ? rangeRow.children[index - 1] : rangeRow.children[index];
            } else {
                rangeRow = !table.rows[index] ? table.rows[index - 1] : table.rows[index];

                if (!rangeRow) return;

                rangeTd = rangeRow.children[0];
            }

            if (!rangeTd) {
                return;
            }

            range.setStart(rangeTd, 0);
            range.setEnd(rangeTd, 0);

            setTimeout(() => {
                this.selection.addRange(range);
            }, 250);
        },

        onDeleteRow() {
            const { table, index } = this.getVariables('tr');

            if (!table || index === undefined) {
                return;
            }

            table.deleteRow(index);

            if (index === 0) {
                this.addResizeHandle(table);
            }

            this.setRangeAfterDelete(table, index, 'row');
            this.keepSelection();
        },

        addResizeHandle(table) {
            this.$nextTick(() => {
                const firstRow = table.rows[0];

                if (!firstRow) {
                    return;
                }

                const rowChildren = Object.values(firstRow.children);

                rowChildren.forEach((child) => {
                    child.innerHTML = this.resizeHandle + child.innerHTML;
                });
            });
        },

        deleteCells(rows, colIdx) {
            rows.forEach((row) => {
                row.deleteCell(colIdx);
            });
        },

        onDeleteTable(event) {
            const node = this.getNode();
            const table = node.closest('table');

            table.remove();
            this.$emit('table-delete', event);
        },

        getVariables(mode = 'td') {
            const node = this.getNode();

            if (!node || !node.closest(mode)) {
                return {};
            }

            const indexConfig = {
                td: 'cellIndex',
                tr: 'rowIndex',
            };

            const table = node.closest('table');
            const index = node.closest(mode)[indexConfig[mode]];

            if (!table || index === undefined) {
                return {};
            }

            return { index, table };
        },

        getNode() {
            this.setSelectionRange();
            this.keepSelection();

            if (!this.selection || !this.selection.anchorNode) {
                return null;
            }

            let node = this.selection.anchorNode;
            if (node.nodeName === '#text') {
                node = node.parentNode;
            }

            return node;
        },

        setSelectionRange() {
            if (this.selection.rangeCount > 0) {
                this.range = this.selection.getRangeAt(0).cloneRange();
            }
        },

        keepSelection() {
            if (!this.range) {
                return;
            }

            this.selection.removeAllRanges();
            this.selection.addRange(this.range);
        },
    },
});
