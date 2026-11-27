import { createWrapper, layout, selectBreakpoint } from './helpers';

describe('grid editor invalid layouts', () => {
    let wrapper: ReturnType<typeof createWrapper>;

    afterEach(() => wrapper?.unmount());

    it.each([
        null,
        {},
        { lg: {} },
        { lg: { columns: [{ type: 'auto' }] } },
        { lg: { areas: [['a1']] } },
    ])('falls back to a usable grid for %p on mount and update', async (gridLayout) => {
        wrapper = createWrapper({ gridLayout });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__column')).toHaveLength(1);
        await wrapper.setProps({ gridLayout: layout('auto auto|a1 a2') });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(2);
        // @ts-expect-error Exercise malformed values received at runtime.
        await wrapper.setProps({ gridLayout });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__column')).toHaveLength(1);
    });

    it('falls back for an incomplete breakpoint override', async () => {
        wrapper = createWrapper({ gridLayout: { ...layout('auto auto|a1 a2'), sm: { columns: [] } } });
        await selectBreakpoint(wrapper, 'sm');
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__column')).toHaveLength(1);
    });

    it('inherits the base grid for a breakpoint stored as null', async () => {
        wrapper = createWrapper({ gridLayout: { ...layout('auto auto|a1 a2'), md: null } });
        await selectBreakpoint(wrapper, 'md');
        expect(wrapper.getComponent({ name: 'BreakpointSelect' }).props('overrides')).toEqual([]);
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__column')).toHaveLength(2);

        await selectBreakpoint(wrapper, 'lg');
        await wrapper.findAll('.sw-experience-studio-grid-editor__add button')[1].trigger('click');
        expect(wrapper.emitted('update:gridLayout')).toEqual([[layout('auto auto|a1 a2;a3 a4')]]);
    });

    it('holds other edits locally until the invalid width is corrected', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2') });
        const column = wrapper.findAll('.sw-experience-studio-grid-editor__column')[0];
        await column.get('select').setValue('px');
        await wrapper.findAll('.sw-experience-studio-grid-editor__add button')[1].trigger('click');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();

        await column.get('input').setValue('300');
        expect(wrapper.emitted('update:gridLayout')).toEqual([[layout('px:300 auto|a1 a2;a3 a4')]]);
    });

    it('keeps held edits when an unchanged layout arrives as a new object', async () => {
        const stored = layout('auto auto|a1 a2');
        wrapper = createWrapper({ gridLayout: stored });
        const column = wrapper.findAll('.sw-experience-studio-grid-editor__column')[0];
        await column.get('select').setValue('px');
        await wrapper.findAll('.sw-experience-studio-grid-editor__add button')[1].trigger('click');

        await wrapper.setProps({ gridLayout: Shopware.Utils.object.cloneDeep(stored) });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(4);
        expect((column.get('select').element as HTMLSelectElement).value).toBe('px');

        await column.get('input').setValue('300');
        expect(wrapper.emitted('update:gridLayout')).toEqual([[layout('px:300 auto|a1 a2;a3 a4')]]);
    });

    it('replaces held edits when the stored layout changes', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2') });
        await wrapper.findAll('.sw-experience-studio-grid-editor__column')[0].get('select').setValue('px');
        await wrapper.findAll('.sw-experience-studio-grid-editor__add button')[1].trigger('click');

        await wrapper.setProps({ gridLayout: layout('auto auto|a1 a1') });
        const columns = wrapper.findAll('.sw-experience-studio-grid-editor__column');
        expect(columns.map((column) => (column.get('select').element as HTMLSelectElement).value)).toEqual([
            'auto',
            'auto',
        ]);
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(1);
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();
    });

    it('holds a grid with an omitted pixel width until the width is supplied', async () => {
        wrapper = createWrapper({
            gridLayout: { lg: { columns: [{ type: 'px' }, { type: 'auto' }], areas: [['a1', 'a2']] } },
        });
        expect(wrapper.getComponent({ name: 'BreakpointSelect' }).props('invalidBreakpoints')).toEqual(['lg']);

        await wrapper.get('.sw-experience-studio-grid-editor__column-entry').trigger('dragstart');
        document.dispatchEvent(new MouseEvent('mouseup'));
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();

        await wrapper.get('.sw-experience-studio-grid-editor__column input').setValue('200');
        expect(wrapper.emitted('update:gridLayout')).toEqual([[layout('px:200 auto|a1 a2')]]);
    });

    it('reports only breakpoints with their own invalid draft widths to the selector', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2~md=auto auto|a1 a2') });
        const select = wrapper.getComponent({ name: 'BreakpointSelect' });
        expect(select.props('invalidBreakpoints')).toEqual([]);

        await wrapper.get('.sw-experience-studio-grid-editor__column-type').setValue('px');
        expect(select.props('invalidBreakpoints')).toEqual(['lg']);
        await selectBreakpoint(wrapper, 'md');
        expect(select.props('invalidBreakpoints')).toEqual(['lg']);

        await selectBreakpoint(wrapper, 'lg');
        await wrapper.get('.sw-experience-studio-grid-editor__column input').setValue('300');
        expect(select.props('invalidBreakpoints')).toEqual([]);
    });
});
