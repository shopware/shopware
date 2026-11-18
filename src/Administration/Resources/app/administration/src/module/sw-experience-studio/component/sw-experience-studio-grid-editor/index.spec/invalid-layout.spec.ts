import { createWrapper, layout } from './helpers';

describe('grid editor invalid layouts', () => {
    let wrapper: ReturnType<typeof createWrapper>;

    afterEach(() => wrapper?.unmount());

    it.each([
        null,
        {},
        { xs: {} },
        { xs: { columns: [{ type: 'auto' }] } },
        { xs: { areas: [['a1']] } },
    ])('falls back to a usable grid for %p on mount and update', async (gridLayout) => {
        wrapper = createWrapper({ gridLayout });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(1);
        await wrapper.setProps({ gridLayout: layout('auto auto|a1 a2') });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(2);
        // @ts-expect-error Exercise malformed values received at runtime.
        await wrapper.setProps({ gridLayout });
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(1);
    });

    it('falls back for an incomplete breakpoint override', async () => {
        wrapper = createWrapper({ gridLayout: { ...layout('auto auto|a1 a2'), sm: { columns: [] } } });
        await wrapper.findAll('.sw-experience-studio-grid-editor__breakpoints button')[1].trigger('click');
        expect(wrapper.findAll('.sw-experience-studio-grid-editor__area')).toHaveLength(1);
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

    it('marks the breakpoint containing an invalid width until it is corrected', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2~md=auto auto|a1 a2') });
        const tabs = wrapper.findAll('.sw-experience-studio-grid-editor__breakpoints button');
        const indicator = '.sw-experience-studio-grid-editor__breakpoint-indicator';

        expect(tabs[0].find(indicator).exists()).toBe(false);
        expect(tabs[2].get(indicator).classes()).not.toContain('is--invalid');
        await wrapper.findAll('.sw-experience-studio-grid-editor__column')[0].get('select').setValue('px');

        expect(tabs[0].find(indicator).exists()).toBe(false);
        expect(tabs[0].attributes('aria-label')).toContain('global.default.error');
        expect(tabs[1].find(indicator).exists()).toBe(false);
        await tabs[2].trigger('click');
        expect(tabs[0].get(indicator).classes()).toContain('is--invalid');
        expect(tabs[2].get(indicator).classes()).not.toContain('is--invalid');

        await tabs[0].trigger('click');
        expect(tabs[0].find(indicator).exists()).toBe(false);
        await wrapper.findAll('.sw-experience-studio-grid-editor__column')[0].get('input').setValue('300');
        expect(tabs[0].find(indicator).exists()).toBe(false);
        expect(tabs[0].attributes('aria-label')).toBeUndefined();
    });

    it('marks a stored invalid width only when its breakpoint is inactive', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto|a1~lg=percent:150 auto|a1 a2') });
        const tabs = wrapper.findAll('.sw-experience-studio-grid-editor__breakpoints button');

        expect(tabs[3].get('.sw-experience-studio-grid-editor__breakpoint-indicator').classes()).toContain('is--invalid');
        expect(tabs[3].attributes('aria-label')).toContain('global.default.error');
        expect(tabs[4].find('.sw-experience-studio-grid-editor__breakpoint-indicator').exists()).toBe(false);
        await tabs[3].trigger('click');
        expect(tabs[3].find('.sw-experience-studio-grid-editor__breakpoint-indicator').exists()).toBe(false);
        await tabs[0].trigger('click');
        expect(tabs[3].get('.sw-experience-studio-grid-editor__breakpoint-indicator').classes()).toContain('is--invalid');
    });
});
