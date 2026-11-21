import { createWrapper, layout, selectBreakpoint } from './index.spec/helpers';

describe('grid pattern settings', () => {
    let wrapper: ReturnType<typeof createWrapper>;

    afterEach(() => wrapper?.unmount());

    it('preserves arbitrary area identities and spans when editing and reloading', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto auto|hero a1 a01;hero sidebar sidebar') });
        const areas = wrapper.findAll('.sw-experience-studio-grid-editor__area');
        expect(areas).toHaveLength(4);
        expect(areas[0].attributes('style')).toContain('grid-row: 1 / span 2');
        expect(areas[3].attributes('style')).toContain('grid-column: 2 / span 2');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();

        await wrapper.findAll('.sw-experience-studio-grid-editor__add button')[1].trigger('click');
        const updated = layout('auto auto auto|a1 a2 a3;a1 a4 a4;a5 a6 a7');
        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([updated]);
        await wrapper.findAll('.sw-experience-studio-grid-editor__area')[0].trigger('click');
        await wrapper.setProps({ gridLayout: updated });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(7);
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area[aria-pressed="true"]')).toHaveLength(1);
    });

    it('cleans up interrupted drags and preserves reordered columns', async () => {
        wrapper = createWrapper({ gridLayout: layout('px:300 auto|a1 a2') });
        const addListener = jest.spyOn(document, 'addEventListener');
        const removeListener = jest.spyOn(document, 'removeEventListener');
        const rows = wrapper.findAll('.sw-experience-studio-grid-editor__column-entry');
        const originalElementFromPoint = Object.getOwnPropertyDescriptor(document, 'elementFromPoint');
        Object.defineProperty(document, 'elementFromPoint', { configurable: true, value: () => rows[1].element });

        try {
            await rows[0].trigger('dragstart');
            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 1 }));
            document.dispatchEvent(new MouseEvent('mousemove', { clientY: 2 }));
            const firstMoveListener = addListener.mock.calls.find(([type]) => type === 'mousemove')?.[1];

            await rows[0].trigger('dragstart');
            expect(removeListener).toHaveBeenCalledWith('mousemove', firstMoveListener);
            expect(wrapper.emitted('update:gridLayout')).toEqual([[layout('auto px:300|a1 a2')]]);

            document.dispatchEvent(new Event('touchcancel'));
            expect(removeListener).toHaveBeenCalledWith('touchcancel', expect.any(Function));
            expect(removeListener).toHaveBeenCalledWith('mousemove', expect.any(Function));
        } finally {
            if (originalElementFromPoint) Object.defineProperty(document, 'elementFromPoint', originalElementFromPoint);
            else Reflect.deleteProperty(document, 'elementFromPoint');
            addListener.mockRestore();
            removeListener.mockRestore();
        }
    });

    it('renders bounds and allocates new ids for a grid with many distinct areas', async () => {
        const rows = Array.from({ length: 100 }, (_, index) => [`left${index}`, `right${index}`]);
        wrapper = createWrapper({ gridLayout: { lg: { columns: [{ type: 'auto' }, { type: 'auto' }], areas: rows } } });
        const areas = wrapper.findAll('.sw-experience-studio-grid-editor__area');
        expect(areas).toHaveLength(200);
        expect(areas[199].attributes('style')).toContain('grid-row: 100 / span 1');
        await wrapper.findAll('.sw-experience-studio-grid-editor__add button')[0].trigger('click');
        const emitted = wrapper.emitted('update:gridLayout')?.at(-1)?.[0] as { lg: { areas: string[][] } };
        expect(emitted.lg.areas[99]).toEqual(['a298', 'a299', 'a300']);
    });

    it('names native width inputs distinctly and requests a numeric keyboard', () => {
        wrapper = createWrapper({ gridLayout: layout('px:100 percent:20 auto|a1 a2 a3') }, true);
        const inputs = wrapper.findAll('.sw-experience-studio-grid-editor__column input');
        expect(inputs.map((input) => input.attributes('aria-label'))).toEqual([
            'sw-experience-studio.gridEditor.width 1',
            'sw-experience-studio.gridEditor.width 2',
        ]);
        expect(inputs.map((input) => input.attributes('inputmode'))).toEqual(['numeric', 'numeric']);
    });

    it('opens the content arrangement directly when a second column is added', async () => {
        wrapper = createWrapper();
        expect(wrapper.find('section').exists()).toBe(false);

        await wrapper.get('.sw-experience-studio-grid-editor__add button').trigger('click');

        const arrangement = wrapper.get('section');
        expect(arrangement.get('h3').text()).toBe('sw-experience-studio.gridEditor.pattern');
        expect(arrangement.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(2);
        await arrangement.get('.sw-experience-studio-grid-editor__add button').trigger('click');

        expect(arrangement.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(4);
        expect(wrapper.emitted('update:gridLayout')).toEqual([
            [layout('auto auto|a1 a2')],
            [layout('auto auto|a1 a2;a3 a4')],
        ]);
    });

    it('updates the visible pattern when undo or redo changes the stored dimensions', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2;a3 a4') });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(4);

        await wrapper.setProps({ gridLayout: layout('auto auto|a1 a2') });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(2);

        await wrapper.setProps({ gridLayout: layout('auto auto|a1 a2;a3 a4') });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(4);
    });

    it('preserves the selection when an equivalent layout has a different key order', async () => {
        wrapper = createWrapper({ gridLayout: layout('px:300 auto|a1 a2') });
        await wrapper.findAll('.sw-experience-studio-grid-editor__area')[0].trigger('click');

        await wrapper.setProps({
            gridLayout: { lg: { areas: [['a1', 'a2']], columns: [{ value: 300, type: 'px' }, { type: 'auto' }] } },
        });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area[aria-pressed="true"]')).toHaveLength(1);

        await wrapper.setProps({ gridLayout: layout('px:300 auto|a1 a1') });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(1);
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area[aria-pressed="true"]')).toHaveLength(0);
    });

    it('emits a smaller pattern when a row or column is removed', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2;a3 a4') });
        await wrapper.findAll('.sw-experience-studio-grid-editor__rows button')[1].trigger('click');
        expect(wrapper.emitted('update:gridLayout')).toEqual([[layout('auto auto|a1 a2')]]);

        await wrapper.findAll('.sw-experience-studio-grid-editor__column')[0].findAll('button')[4].trigger('click');
        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([layout('auto|a1')]);
        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('keeps the final row and column', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2') });
        expect(wrapper.get('.sw-experience-studio-grid-editor__rows button').attributes('disabled')).toBeDefined();

        await wrapper.setProps({ gridLayout: layout('auto|a1') });
        const columnButtons = wrapper.get('.sw-experience-studio-grid-editor__column').findAll('button');
        expect(columnButtons.at(-1)?.attributes('disabled')).toBeDefined();
    });

    it('creates a breakpoint override on the first edit and returns to the base grid on undo', async () => {
        const base = layout('percent:20 auto|a1 a2;a1 a2');
        wrapper = createWrapper({ gridLayout: base });
        const select = wrapper.getComponent({ name: 'BreakpointSelect' });
        const areas = () => wrapper.findAll('.sw-experience-studio-grid-editor__area');

        await selectBreakpoint(wrapper, 'md');
        expect(areas()).toHaveLength(2);
        expect(select.props('overrides')).toEqual([]);
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
        await areas()[0].trigger('click');
        expect(select.props('overrides')).toEqual([]);
        await wrapper.findAll('.sw-experience-studio-grid-editor__add button')[1].trigger('click');

        const changed = layout('percent:20 auto|a1 a2;a1 a2~md=percent:20 auto|a1 a2;a1 a2;a3 a4');
        expect(wrapper.emitted('update:gridLayout')).toEqual([[changed]]);
        expect(select.props('overrides')).toEqual(['md']);
        await wrapper.setProps({ gridLayout: changed });
        await selectBreakpoint(wrapper, 'xl');
        expect(areas()).toHaveLength(2);
        await selectBreakpoint(wrapper, 'md');
        expect(areas()).toHaveLength(4);
        await wrapper.setProps({ gridLayout: base });
        expect(areas()).toHaveLength(2);
        expect(select.props('overrides')).toEqual([]);
    });

    it('only shows the arrangement for breakpoints that resolve to multiple columns', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2~sm=auto|a1~xl=auto auto auto|a1 a2 a3') });
        const select = wrapper.getComponent({ name: 'BreakpointSelect' });
        const areas = () => wrapper.findAll('.sw-experience-studio-grid-editor__area');

        expect(select.props('modelValue')).toBe('lg');
        expect(select.props('overrides')).toEqual(['sm', 'xl']);
        expect(areas()).toHaveLength(2);

        await selectBreakpoint(wrapper, 'sm');
        expect(wrapper.find('section').exists()).toBe(false);
        await selectBreakpoint(wrapper, 'md');
        expect(areas()).toHaveLength(2);
        await selectBreakpoint(wrapper, 'xxl');
        expect(areas()).toHaveLength(3);
        await selectBreakpoint(wrapper, 'xs');
        expect(wrapper.find('section').exists()).toBe(false);
        expect(select.props('overrides')).toEqual(['sm', 'xl']);
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
    });

    it('resets the selected grid to its inherited SM pattern and preserves other overrides', async () => {
        const base = 'auto auto|a1 a2~sm=auto|a1~xxl=auto auto|a1 a1';
        wrapper = createWrapper({ gridLayout: layout(`${base}~xs=auto auto auto|a1 a2 a3`) });
        const selector = wrapper.getComponent({ name: 'BreakpointSelect' });

        await selectBreakpoint(wrapper, 'xs');
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(3);
        await selector.get('button').trigger('click');

        expect(wrapper.emitted('update:gridLayout')).toEqual([[layout(base)]]);
        expect(selector.props('modelValue')).toBe('xs');
        expect(selector.props('overrides')).toEqual(['sm', 'xxl']);
        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('keeps invalid drafts local to the breakpoint being edited', async () => {
        wrapper = createWrapper({ gridLayout: layout('percent:20 auto|a1 a2~xl=auto|a1') });
        const input = () => wrapper.get<HTMLInputElement>('.sw-experience-studio-grid-editor__column input');

        await selectBreakpoint(wrapper, 'sm');
        await input().setValue('');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();

        await selectBreakpoint(wrapper, 'lg');
        expect(input().element.value).toBe('20');
        await selectBreakpoint(wrapper, 'sm');
        expect(input().element.value).toBe('');
        await input().setValue('30');
        expect(wrapper.emitted('update:gridLayout')).toEqual([
            [layout('percent:20 auto|a1 a2~sm=percent:30 auto|a1 a2~xl=auto|a1')],
        ]);
    });

    it('preserves incomplete input containing a breakpoint separator', async () => {
        wrapper = createWrapper({ gridLayout: layout('percent:20 auto|a1 a2') });
        await wrapper.get('.sw-experience-studio-grid-editor__column input').setValue('~');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
        await selectBreakpoint(wrapper, 'sm');
        await selectBreakpoint(wrapper, 'lg');
        expect((wrapper.get('.sw-experience-studio-grid-editor__column input').element as HTMLInputElement).value).toBe('~');
    });

    it('disables adding tracks after twelve rows and columns', () => {
        wrapper = createWrapper({
            gridLayout: layout(
                `${Array(12).fill('auto').join(' ')}|${Array(12).fill(Array(12).fill('a1').join(' ')).join(';')}`,
            ),
        });
        const add = wrapper.findAll('.sw-experience-studio-grid-editor__add button');

        expect(add[0].attributes('disabled')).toBeDefined();
        expect(add[1].attributes('disabled')).toBeDefined();
    });

    it('merges adjacent areas and splits them in the only possible direction', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2;a3 a4') });
        const areas = wrapper.findAll('.sw-experience-studio-grid-editor__area');
        await areas[0].trigger('click');
        await areas[1].trigger('click');
        await wrapper.findAll('.sw-experience-studio-grid-editor__actions button')[1].trigger('click');
        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([
            layout('auto auto|a1 a1;a2 a3'),
        ]);
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(3);
        expect(document.activeElement).toBe(wrapper.findAll('.sw-experience-studio-grid-editor__area')[0].element);

        await wrapper.setProps({ gridLayout: layout('auto auto|a1 a1;a2 a3') });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area[aria-pressed="true"]')).toHaveLength(1);

        await wrapper.findAll('.sw-experience-studio-grid-editor__actions button')[0].trigger('click');
        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([
            layout('auto auto|a1 a2;a3 a4'),
        ]);
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area[aria-pressed="true"]')).toHaveLength(2);
    });

    it('selects only the last area when a multi-selection is not rectangular', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2;a3 a4') });
        const areas = wrapper.findAll('.sw-experience-studio-grid-editor__area');
        await areas[0].trigger('click');
        await areas[3].trigger('click');

        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area[aria-pressed="true"]')).toHaveLength(1);
        expect(areas[3].attributes('aria-pressed')).toBe('true');
    });

    it('deselects an area when it is clicked again', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2') });
        const area = wrapper.findAll('.sw-experience-studio-grid-editor__area')[0];

        await area.trigger('click');
        expect(area.attributes('aria-pressed')).toBe('true');
        await area.trigger('click');
        expect(area.attributes('aria-pressed')).toBe('false');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
    });

    it('does not allow splitting a single-cell area', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2') });
        await wrapper.get('.sw-experience-studio-grid-editor__area').trigger('click');
        const split = wrapper.get('.sw-experience-studio-grid-editor__actions button');
        expect(split.attributes('disabled')).toBeDefined();
        split.element.removeAttribute('disabled');
        await split.trigger('click');
        // eslint-disable-next-line sw-test-rules/no-vm-access -- The UI hides split-all for a single-cell area, so call it to cover the defensive handler guard.
        (wrapper.vm as unknown as { split: (direction: 'all') => void }).split('all');
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(2);
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
    });

    it('shows secondary Split and Merge actions only while an area is selected', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2') });
        const area = wrapper.get('.sw-experience-studio-grid-editor__area');
        const actions = '.sw-experience-studio-grid-editor__actions';

        expect(wrapper.find(actions).exists()).toBe(false);
        await area.trigger('click');
        const buttons = wrapper.get(actions).findAll('button');
        expect(buttons.map((button) => button.text())).toEqual([
            'sw-experience-studio.gridEditor.split',
            'sw-experience-studio.gridEditor.merge',
        ]);
        expect(buttons.every((button) => button.attributes('variant') === 'secondary')).toBe(true);

        await area.trigger('click');
        expect(wrapper.find(actions).exists()).toBe(false);
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
    });

    it('handles focusing an area when the current layout has no cells', async () => {
        wrapper = createWrapper({
            gridLayout: { lg: { columns: [{ type: 'auto' }], areas: [] } },
        });

        await wrapper.findAll('.sw-experience-studio-grid-editor__add button')[0].trigger('click');

        expect(wrapper.emitted('update:gridLayout')).toHaveLength(1);
    });

    it('splits a one-column area with multiple rows in all directions', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2;a1 a2') });
        await wrapper.get('.sw-experience-studio-grid-editor__area').trigger('click');
        // eslint-disable-next-line sw-test-rules/no-vm-access -- Exercise the split-all handler with an area shape whose UI only exposes row splitting.
        (wrapper.vm as unknown as { split: (direction: 'all') => void }).split('all');
        await Promise.resolve();

        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([layout('auto auto|a1 a2;a3 a2')]);
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area[aria-pressed="true"]')).toHaveLength(2);
    });

    it('merges rectangular areas that already span several columns', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto auto|a1 a2 a2;a1 a3 a3') });
        const areas = wrapper.findAll('.sw-experience-studio-grid-editor__area');
        await areas[1].trigger('click');
        await areas[2].trigger('click');
        await wrapper.findAll('.sw-experience-studio-grid-editor__actions button')[1].trigger('click');

        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([
            layout('auto auto auto|a1 a2 a2;a1 a2 a2'),
        ]);
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(2);
    });

    it('shrinks areas that cross a removed row', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto auto|a1 a2 a3;a1 a4 a4') });
        await wrapper.findAll('.sw-experience-studio-grid-editor__rows button')[0].trigger('click');

        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([
            layout('auto auto auto|a1 a2 a2'),
        ]);
    });

    it('shrinks areas that cross a removed column', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto auto|a1 a2 a2;a1 a3 a3') });
        await wrapper.findAll('.sw-experience-studio-grid-editor__column')[1].findAll('button')[4].trigger('click');

        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([
            layout('auto auto|a1 a2;a1 a3'),
        ]);
    });

    it('keeps incomplete widths in the pattern until they are corrected', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2') });
        const column = wrapper.findAll('.sw-experience-studio-grid-editor__column')[0];

        await column.get('select').setValue('px');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
        await column.get('input').setValue('-');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
        await column.get('input').setValue('300');
        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([
            { lg: { columns: [{ type: 'px', value: 300 }, { type: 'auto' }], areas: [['a1', 'a2']] } },
        ]);
        await wrapper.setProps({ gridLayout: layout('px:300 auto|a1 a2') });
        await wrapper.setProps({ gridLayout: layout('px:- auto|a1 a2') });
        expect((column.get('input').element as HTMLInputElement).value).toBe('-');
    });

    it('shows invalid width feedback below the row only while the input is blurred', async () => {
        wrapper = createWrapper({ gridLayout: layout('percent:20 auto|a1 a2') });
        const entry = wrapper.findAll('.sw-experience-studio-grid-editor__column-entry')[0];
        const input = entry.get('input');

        await input.trigger('focus');
        await input.setValue('-');
        expect(entry.find('.sw-experience-studio-grid-editor__column-error').exists()).toBe(false);
        expect(input.element.parentElement?.classList.contains('has--error')).toBe(false);

        await input.trigger('blur');
        const error = entry.get('.sw-experience-studio-grid-editor__column-error');
        expect(error.text()).toContain('invalidPercentWidth');
        expect(input.element.parentElement?.classList.contains('has--error')).toBe(true);
        expect(error.find('mt-icon-stub').exists()).toBe(false);
        expect(entry.get('.sw-experience-studio-grid-editor__column').find('[role="alert"]').exists()).toBe(false);

        await input.trigger('focus');
        expect(entry.find('.sw-experience-studio-grid-editor__column-error').exists()).toBe(false);
        expect(input.element.parentElement?.classList.contains('has--error')).toBe(false);
        await input.setValue('30');
        await input.trigger('blur');
        expect(entry.find('.sw-experience-studio-grid-editor__column-error').exists()).toBe(false);
        expect(input.element.parentElement?.classList.contains('has--error')).toBe(false);
    });

    it('shows a pixel-specific message for an invalid pixel width', () => {
        wrapper = createWrapper({ gridLayout: layout('px:0 auto|a1 a2') });
        const error = wrapper.get('.sw-experience-studio-grid-editor__column-error');

        expect(error.text()).toContain('invalidPixelWidth');
    });

    it('moves widths without moving areas and restores their order on undo', async () => {
        wrapper = createWrapper({ gridLayout: layout('px:300 percent:20 auto|a1 a2 a2;a1 a3 a3') });
        await wrapper.findAll('.sw-experience-studio-grid-editor__column')[0].findAll('button')[3].trigger('click');
        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([
            layout('percent:20 px:300 auto|a1 a2 a2;a1 a3 a3'),
        ]);
        await wrapper.setProps({ gridLayout: layout('percent:20 px:300 auto|a1 a2 a2;a1 a3 a3') });
        await wrapper.setProps({ gridLayout: layout('px:300 percent:20 auto|a1 a2 a2;a1 a3 a3') });
        const columns = wrapper.findAll('.sw-experience-studio-grid-editor__column');
        expect(columns.map((column) => (column.get('select').element as HTMLSelectElement).value)).toEqual([
            'px',
            'percent',
            'auto',
        ]);
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(3);
    });

    it('prevents removing the final flexible column while a pixel column remains', () => {
        wrapper = createWrapper({ gridLayout: layout('px:300 auto|a1 a2') });
        const columns = wrapper.findAll('.sw-experience-studio-grid-editor__column');
        expect(columns[1].findAll('button').at(-1)?.attributes('disabled')).toBeDefined();
        expect(columns[0].findAll('button').at(-1)?.attributes('disabled')).toBeUndefined();
        expect(columns[1].get('option[value="px"]').attributes('disabled')).toBeDefined();
    });

    it('starts twelfths at one and clears the previous value when changing to percent', async () => {
        wrapper = createWrapper({ gridLayout: layout('percent:20 auto|a1 a2') });
        const column = wrapper.findAll('.sw-experience-studio-grid-editor__column')[0];
        await column.get('select').setValue('fraction');
        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([
            layout('fraction:1 auto|a1 a2'),
        ]);
        expect((column.findAll('select')[0].element as HTMLSelectElement).value).toBe('1');
        await column.findAll('select')[0].setValue('4');
        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([
            layout('fraction:4 auto|a1 a2'),
        ]);
        await column.findAll('select')[1].setValue('percent');
        expect(wrapper.emitted('update:gridLayout')).toHaveLength(2);
        expect((column.get('input').element as HTMLInputElement).value).toBe('');
    });

    it('warns when relative widths leave no space for auto or exceed 100 percent', async () => {
        wrapper = createWrapper({ gridLayout: layout('percent:25 fraction:9 auto|a1 a2 a3') });
        expect(wrapper.get('.sw-experience-studio-grid-editor__warning').text()).toContain('noAutoSpace');

        await wrapper.setProps({ gridLayout: layout('percent:26 fraction:9 auto|a1 a2 a3') });
        expect(wrapper.get('.sw-experience-studio-grid-editor__warning').text()).toContain('tooMuch');

        await wrapper.setProps({ gridLayout: layout('percent:33 percent:33 percent:33|a1 a2 a3') });
        expect(wrapper.find('.sw-experience-studio-grid-editor__warning').exists()).toBe(false);
    });

    it.each(['percent', 'px'])('keeps the relative-width warning when a %s draft is invalid', async (type) => {
        wrapper = createWrapper({ gridLayout: layout(`${type}:- percent:80 percent:80 auto|a1 a2 a3 a4`) });
        expect(wrapper.get('.sw-experience-studio-grid-editor__warning').text()).toContain('tooMuch');

        await wrapper.setProps({ gridLayout: layout(`${type}:- percent:50 percent:50 auto|a1 a2 a3 a4`) });
        expect(wrapper.get('.sw-experience-studio-grid-editor__warning').text()).toContain('noAutoSpace');
    });

    it.each([
        ['split', 'auto auto|a1 a2;a3 a4'],
        ['splitRows', 'auto auto|a1 a1;a2 a2'],
        ['splitColumns', 'auto auto|a1 a2;a1 a2'],
    ])('splits a two-dimensional area with %s', async (action, expected) => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a1;a1 a1') });
        await wrapper.get('.sw-experience-studio-grid-editor__area').trigger('click');
        const buttons = wrapper.findAll('.sw-experience-studio-grid-editor__actions button');
        const actionButtons = buttons.filter((button) => button.text() === `sw-experience-studio.gridEditor.${action}`);
        await actionButtons[action === 'split' ? 1 : 0].trigger('click');

        expect(wrapper.emitted('update:gridLayout')?.at(-1)).toEqual([layout(expected)]);
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area[aria-pressed="true"]')).toHaveLength(
            action === 'split' ? 4 : 2,
        );
    });
});
