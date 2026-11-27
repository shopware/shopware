import { MtText } from '@shopware-ag/meteor-component-library';
import type {
    ContentSystemBreakpoint,
    ContentSystemBreakpointFallbacks,
} from 'src/core/service/api/content-system-element-type.api.service';
import { resolveBreakpointValue } from '../../util/breakpoint.util';
import BreakpointSelect, { BASE_BREAKPOINT, orderedBreakpoints } from '../sw-experience-studio-breakpoint-select';
import template from './sw-experience-studio-grid-editor.html.twig';
import './sw-experience-studio-grid-editor.scss';

type Column = { id: number; type: 'auto' | 'px' | 'percent' | 'fraction'; value: string | null };
type Pattern = { columns: Column[]; cells: number[][] };
type LayoutColumn = { type: Column['type']; value?: number | string };
type LayoutConfiguration = { columns: LayoutColumn[]; areas: string[][] };
type GridLayout = Partial<Record<ContentSystemBreakpoint, LayoutConfiguration>>;
type BreakpointPatterns = Partial<Record<ContentSystemBreakpoint, Pattern>> & { lg: Pattern };

function initialPattern(value?: LayoutConfiguration | null): Pattern {
    if (!value || !Array.isArray(value.columns) || !Array.isArray(value.areas)) {
        return { columns: [{ id: 1, type: 'auto', value: null }], cells: [[1]] };
    }
    const ids = new Map<string, number>();
    return {
        columns: value.columns.map((column, index) => ({
            id: index + 1,
            type: column.type,
            value: column.value === undefined ? null : String(column.value),
        })),
        cells: value.areas.map((row) =>
            row.map((cell) => {
                if (!ids.has(cell)) ids.set(cell, ids.size + 1);
                return ids.get(cell) as number;
            }),
        ),
    };
}

function encodePattern(pattern: Pattern): LayoutConfiguration {
    return {
        columns: pattern.columns.map((column) => {
            if (column.type === 'auto') return { type: 'auto' };
            const value = column.value ?? '';
            return {
                type: column.type,
                value: /^[1-9][0-9]*$/.test(value) && Number.isSafeInteger(Number(value)) ? Number(value) : value,
            };
        }),
        areas: pattern.cells.map((row) => row.map((id) => `a${id}`)),
    };
}

function parsePatterns(value: GridLayout = {}): BreakpointPatterns {
    const patterns: BreakpointPatterns = { lg: initialPattern(value.lg) };
    for (const breakpoint of orderedBreakpoints) {
        if (breakpoint !== BASE_BREAKPOINT && value[breakpoint] != null) {
            patterns[breakpoint] = initialPattern(value[breakpoint]);
        }
    }

    return patterns;
}

function encodePatterns(patterns: BreakpointPatterns): GridLayout {
    const result: GridLayout = { lg: encodePattern(patterns.lg) };
    for (const breakpoint of orderedBreakpoints) {
        const pattern = patterns[breakpoint];
        if (breakpoint !== BASE_BREAKPOINT && pattern) result[breakpoint] = encodePattern(pattern);
    }

    return result;
}

function invalidWidth(column: Column): boolean {
    if (column.type === 'auto') return false;
    const value = column.value ?? '';
    if (!/^[1-9][0-9]*$/.test(value)) return true;
    const numeric = Number(value);
    return (
        !Number.isSafeInteger(numeric) ||
        (column.type === 'percent' && numeric > 100) ||
        (column.type === 'fraction' && numeric > 12)
    );
}

function relativeWidthUnits(columns: Column[]): number {
    return columns.reduce((sum, column) => {
        if (invalidWidth(column)) return sum;
        if (column.type === 'percent') return sum + Number(column.value) * 3;
        if (column.type === 'fraction') return sum + Number(column.value) * 25;
        return sum;
    }, 0);
}

function isPastDragMidpoint(movingDown: boolean, y: number, previousY: number, midpoint: number): boolean {
    return movingDown ? y >= midpoint && y > previousY : y <= midpoint && y < previousY;
}

function nextAreaId(pattern: Pattern): number {
    return pattern.cells.reduce((max, row) => row.reduce((rowMax, id) => Math.max(rowMax, id), max), 0) + 1;
}

function copyPattern(pattern: Pattern): Pattern {
    return { columns: pattern.columns.map((column) => ({ ...column })), cells: pattern.cells.map((row) => [...row]) };
}

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    components: {
        BreakpointSelect,
        MtText,
    },

    directives: {
        numericInput: {
            mounted(element: HTMLElement) {
                element.querySelector('input')?.setAttribute('inputmode', 'numeric');
            },
        },
    },

    props: {
        label: { type: String, default: '' },
        breakpointFallbacks: {
            type: Object as PropType<ContentSystemBreakpointFallbacks>,
            default: () => ({}),
        },
        gridLayout: {
            type: Object as () => GridLayout,
            required: false,
            default: () => ({}),
        },
        allowEdit: { type: Boolean, required: false, default: false },
    },

    emits: ['update:gridLayout'],

    data() {
        const patterns = parsePatterns(this.gridLayout ?? {});
        return {
            breakpoint: BASE_BREAKPOINT as ContentSystemBreakpoint,
            patterns,
            syncedLayoutJson: JSON.stringify(encodePatterns(patterns)),
            selected: [] as number[],
            nextId: nextAreaId(patterns.lg),
            nextColumnId: patterns.lg.columns.length + 1,
            focusedColumnId: null as number | null,
            draggingColumnId: null as number | null,
            dragStartPattern: '',
            dragStartedInherited: false,
            lastDragY: null as number | null,
            dragMoveListener: null as EventListener | null,
            dragEndListener: null as EventListener | null,
        };
    },

    computed: {
        overrides(): ContentSystemBreakpoint[] {
            return orderedBreakpoints.filter(
                (breakpoint) => breakpoint !== BASE_BREAKPOINT && this.patterns[breakpoint] !== undefined,
            );
        },
        invalidBreakpoints(): ContentSystemBreakpoint[] {
            return orderedBreakpoints.filter((breakpoint) => this.patterns[breakpoint]?.columns.some(invalidWidth));
        },
        pattern(): Pattern {
            return resolveBreakpointValue(this.patterns, this.breakpoint, this.breakpointFallbacks) ?? initialPattern();
        },
        areas(): { id: number; number: number; row: number; column: number; rowSpan: number; columnSpan: number }[] {
            const bounds = new Map<
                number,
                { id: number; number: number; row: number; column: number; rowSpan: number; columnSpan: number }
            >();
            this.pattern.cells.forEach((row, y) =>
                row.forEach((id, x) => {
                    const area = bounds.get(id);
                    if (!area) {
                        bounds.set(id, { id, number: bounds.size + 1, row: y, column: x, rowSpan: 1, columnSpan: 1 });
                        return;
                    }
                    const bottom = Math.max(area.row + area.rowSpan - 1, y);
                    const right = Math.max(area.column + area.columnSpan - 1, x);
                    area.row = Math.min(area.row, y);
                    area.column = Math.min(area.column, x);
                    area.rowSpan = bottom - area.row + 1;
                    area.columnSpan = right - area.column + 1;
                }),
            );
            return [...bounds.values()];
        },
        selectedArea() {
            return this.selected.length === 1 ? this.areas.find((area) => area.id === this.selected[0]) : undefined;
        },
        canMerge(): boolean {
            if (this.selected.length < 2) return false;
            const selected = new Set(this.selected);
            const bounds = this.areas.reduce(
                (result, area) => {
                    if (selected.has(area.id)) {
                        result.top = Math.min(result.top, area.row);
                        result.left = Math.min(result.left, area.column);
                        result.bottom = Math.max(result.bottom, area.row + area.rowSpan);
                        result.right = Math.max(result.right, area.column + area.columnSpan);
                    }
                    return result;
                },
                { top: Infinity, left: Infinity, bottom: -Infinity, right: -Infinity },
            );
            return this.pattern.cells
                .slice(bounds.top, bounds.bottom)
                .every((row) => row.slice(bounds.left, bounds.right).every((id) => selected.has(id)));
        },
        canMove(): boolean {
            return this.allowEdit && this.pattern.columns.length > 1;
        },
        fractionOptions(): { value: number; label: string }[] {
            return Array.from({ length: 12 }, (_, index) => ({ value: index + 1, label: String(index + 1) }));
        },
        relativeUnits(): number {
            return relativeWidthUnits(this.pattern.columns);
        },
        hasAuto(): boolean {
            return this.pattern.columns.some((column) => column.type === 'auto');
        },
    },

    watch: {
        gridLayout(): void {
            this.syncPattern();
        },
    },

    beforeUnmount() {
        this.removeDragListeners();
    },

    methods: {
        syncPattern(): void {
            const incoming = parsePatterns(this.gridLayout ?? {});
            const incomingJson = JSON.stringify(encodePatterns(incoming));
            // WHY: Each layout edit clones the whole layout, so an unchanged grid arrives as a new object.
            // Comparing with local state instead would discard drafts that are held while a width is invalid.
            if (incomingJson === this.syncedLayoutJson) return;
            this.syncedLayoutJson = incomingJson;
            this.removeDragListeners();
            this.draggingColumnId = null;
            this.patterns = incoming;
            const pattern = this.pattern;
            this.nextId = nextAreaId(pattern);
            this.nextColumnId = pattern.columns.length + 1;
            this.selected = [];
        },
        commitPattern(): void {
            const ids = new Map<number, number>();
            this.pattern.cells = this.pattern.cells.map((row) =>
                row.map((id) => {
                    if (!ids.has(id)) ids.set(id, ids.size + 1);
                    return ids.get(id) as number;
                }),
            );
            this.selected = this.selected.map((id) => ids.get(id)).filter((id): id is number => id !== undefined);
            this.nextId = ids.size + 1;
            this.emitPatterns();
        },
        emitPatterns(): void {
            if (Object.values(this.patterns).some((pattern) => pattern.columns.some(invalidWidth))) return;
            const layout = encodePatterns(this.patterns);
            this.syncedLayoutJson = JSON.stringify(layout);
            this.$emit('update:gridLayout', layout);
        },
        focusArea(id: number | undefined): void {
            if (id === undefined) return;
            void this.$nextTick(() => {
                (this.$el as HTMLElement).querySelector<HTMLButtonElement>(`[data-area-id="${id}"]`)?.focus();
            });
        },
        setBreakpoint(breakpoint: ContentSystemBreakpoint): void {
            this.onColumnDragEnd();
            this.breakpoint = breakpoint;
            this.selected = [];
            this.focusedColumnId = null;
            this.nextId = nextAreaId(this.pattern);
            this.nextColumnId = Math.max(...this.pattern.columns.map((column) => column.id)) + 1;
        },
        resetBreakpoint(): void {
            if (!this.allowEdit || this.draggingColumnId !== null || !this.overrides.includes(this.breakpoint)) return;

            delete this.patterns[this.breakpoint];
            this.setBreakpoint(this.breakpoint);
            this.emitPatterns();
        },
        getEditablePattern(): Pattern {
            this.patterns[this.breakpoint] ??= copyPattern(this.pattern);
            return this.pattern;
        },
        addColumn(): void {
            if (!this.allowEdit || this.pattern.columns.length >= 12) return;
            const pattern = this.getEditablePattern();
            pattern.columns.push({ id: this.nextColumnId++, type: 'auto', value: null });
            pattern.cells.forEach((row) => row.push(this.nextId++));
            this.selected = [];
            this.commitPattern();
            this.focusArea(this.pattern.cells[0]?.at(-1));
        },
        addRow(): void {
            if (!this.allowEdit || this.pattern.cells.length >= 12) return;
            const pattern = this.getEditablePattern();
            pattern.cells.push(pattern.columns.map(() => this.nextId++));
            this.selected = [];
            this.commitPattern();
            this.focusArea(this.pattern.cells.at(-1)?.[0]);
        },
        removeRow(index: number): void {
            if (!this.allowEdit || this.pattern.cells.length <= 1) return;
            const pattern = this.getEditablePattern();
            pattern.cells.splice(index, 1);
            this.selected = [];
            this.commitPattern();
            this.focusArea(this.pattern.cells[Math.min(index, this.pattern.cells.length - 1)]?.[0]);
        },
        canRemoveColumn(index: number): boolean {
            const remaining = this.pattern.columns.filter((_, position) => position !== index);
            return (
                this.allowEdit &&
                remaining.length > 0 &&
                (!remaining.some((column) => column.type === 'px') || remaining.some((column) => column.type !== 'px'))
            );
        },
        typeOptions(index: number) {
            const otherFlexible = this.pattern.columns.some(
                (column, position) => position !== index && column.type !== 'px',
            );
            return [
                { value: 'auto', label: this.$t('sw-experience-studio.gridEditor.typeAuto') },
                { value: 'px', label: 'px', disabled: !otherFlexible },
                { value: 'percent', label: '%' },
                { value: 'fraction', label: '/12' },
            ];
        },
        removeColumn(index: number): void {
            if (!this.canRemoveColumn(index)) return;
            const pattern = this.getEditablePattern();
            pattern.columns.splice(index, 1);
            pattern.cells.forEach((row) => row.splice(index, 1));
            this.selected = [];
            this.commitPattern();
            this.focusArea(this.pattern.cells[0]?.[Math.min(index, this.pattern.columns.length - 1)]);
        },
        moveColumn(from: number, to: number): void {
            if (!this.canMove || to < 0 || to >= this.pattern.columns.length || from === to) return;
            const pattern = this.getEditablePattern();
            const column = pattern.columns.splice(from, 1)[0];
            pattern.columns.splice(to, 0, column);
            if (this.draggingColumnId === null) this.commitPattern();
        },
        onColumnDragStart(config: { data: number }): void {
            this.onColumnDragEnd();
            if (!this.canMove) return;

            this.draggingColumnId = config.data;
            this.dragStartPattern = JSON.stringify(encodePattern(this.pattern));
            this.dragStartedInherited = this.patterns[this.breakpoint] === undefined;
            this.lastDragY = null;
            this.dragMoveListener = (event) => this.onColumnDragMove(event as MouseEvent | TouchEvent);
            this.dragEndListener = () => this.onColumnDragEnd();
            document.addEventListener('mousemove', this.dragMoveListener);
            document.addEventListener('touchmove', this.dragMoveListener);
            document.addEventListener('mouseup', this.dragEndListener, { once: true });
            document.addEventListener('touchend', this.dragEndListener, { once: true });
            document.addEventListener('touchcancel', this.dragEndListener, { once: true });
        },
        onColumnDragMove(event: MouseEvent | TouchEvent): void {
            const pointer = event instanceof MouseEvent ? event : event.touches[0];
            if (!pointer || this.draggingColumnId === null) return;
            const previousY = this.lastDragY;
            this.lastDragY = pointer.clientY;
            if (previousY === null || previousY === pointer.clientY) return;

            const row = document
                .elementFromPoint(pointer.clientX, pointer.clientY)
                ?.closest<HTMLElement>('.sw-experience-studio-grid-editor__column-entry[data-column-id]');
            if (!row || !(this.$el as HTMLElement).contains(row)) return;
            const from = this.pattern.columns.findIndex((column) => column.id === this.draggingColumnId);
            const to = this.pattern.columns.findIndex((column) => column.id === Number(row.dataset.columnId));
            if (from < 0 || to < 0 || from === to) return;
            const bounds = row.getBoundingClientRect();
            const midpoint = bounds.top + bounds.height / 2;
            if (!isPastDragMidpoint(to > from, pointer.clientY, previousY, midpoint)) return;
            this.moveColumn(from, to);
        },
        removeDragListeners(): void {
            if (this.dragMoveListener) {
                document.removeEventListener('mousemove', this.dragMoveListener);
                document.removeEventListener('touchmove', this.dragMoveListener);
            }
            if (this.dragEndListener) {
                document.removeEventListener('mouseup', this.dragEndListener);
                document.removeEventListener('touchend', this.dragEndListener);
                document.removeEventListener('touchcancel', this.dragEndListener);
            }
            this.dragMoveListener = null;
            this.dragEndListener = null;
        },
        onColumnDragEnd(): void {
            this.removeDragListeners();
            if (this.draggingColumnId !== null) {
                this.draggingColumnId = null;
                if (this.dragStartPattern !== JSON.stringify(encodePattern(this.pattern))) {
                    this.commitPattern();
                } else if (this.dragStartedInherited) {
                    delete this.patterns[this.breakpoint];
                }
            }
        },
        toggleArea(id: number): void {
            if (!this.allowEdit) return;
            if (this.selected.includes(id)) {
                this.selected = this.selected.filter((item) => item !== id);
                return;
            }

            this.selected = [...this.selected, id];
            if (this.selected.length > 1 && !this.canMerge) {
                this.selected = [id];
            }
        },
        merge(): void {
            if (!this.allowEdit || !this.canMerge) return;
            const pattern = this.getEditablePattern();
            const id = this.selected[0];
            pattern.cells = pattern.cells.map((row) => row.map((value) => (this.selected.includes(value) ? id : value)));
            this.selected = [id];
            this.commitPattern();
            this.focusArea(this.selected[0]);
        },
        split(direction: 'all' | 'columns' | 'rows'): void {
            const area = this.selectedArea;
            if (
                !this.allowEdit ||
                !area ||
                (direction === 'columns' && area.columnSpan < 2) ||
                (direction === 'rows' && area.rowSpan < 2) ||
                (direction === 'all' && area.columnSpan === 1 && area.rowSpan === 1)
            )
                return;
            const pattern = this.getEditablePattern();
            const ids = new Map<string, number>();
            pattern.cells = pattern.cells.map((row, y) =>
                row.map((value, x) => {
                    if (value !== area.id) return value;
                    const key = direction === 'all' ? `${x}:${y}` : String(direction === 'columns' ? x : y);
                    if (!ids.has(key)) ids.set(key, this.nextId++);
                    return ids.get(key) as number;
                }),
            );
            this.selected = [...ids.values()];
            this.commitPattern();
            this.focusArea(this.selected[0]);
        },
        setColumnType(index: number, type: Column['type']): void {
            if (
                this.allowEdit &&
                type !== this.pattern.columns[index].type &&
                (type !== 'px' || this.pattern.columns.some((other, position) => position !== index && other.type !== 'px'))
            ) {
                const column = this.getEditablePattern().columns[index];
                column.type = type;
                column.value = type === 'fraction' ? '1' : null;
                this.focusedColumnId = type === 'px' || type === 'percent' ? column.id : null;
                if (type === 'px' || type === 'percent') {
                    void this.$nextTick(() => {
                        const row = (this.$el as HTMLElement).querySelector(`[data-column-id="${column.id}"]`);
                        row?.querySelector<HTMLInputElement>(
                            '.sw-experience-studio-grid-editor__column-width input',
                        )?.focus();
                    });
                }
                this.commitPattern();
            }
        },
        setColumnValue(index: number, value: string | number | null): void {
            const normalized = typeof value === 'number' ? String(value) : value;
            if (!this.allowEdit || this.pattern.columns[index].value === normalized) return;

            this.getEditablePattern().columns[index].value = normalized;
            this.commitPattern();
        },
        invalidColumn(column: Column): boolean {
            return invalidWidth(column);
        },
    },
});
