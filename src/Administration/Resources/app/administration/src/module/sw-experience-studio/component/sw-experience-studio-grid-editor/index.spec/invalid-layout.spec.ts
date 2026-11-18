import { createWrapper, layout } from './helpers';

describe('grid editor invalid layouts', () => {
    let wrapper: ReturnType<typeof createWrapper>;

    afterEach(() => wrapper?.unmount());

    it('holds other edits locally until the invalid width is corrected', async () => {
        wrapper = createWrapper({ gridLayout: layout('auto auto|a1 a2') });
        const column = wrapper.findAll('.sw-experience-studio-grid-editor__column')[0];
        await column.get('select').setValue('px');
        await wrapper.findAll('.sw-experience-studio-grid-editor__add button')[1].trigger('click');
        expect(wrapper.emitted('update:gridLayout')).toBeUndefined();

        await column.get('input').setValue('300');
        expect(wrapper.emitted('update:gridLayout')).toEqual([[layout('px:300 auto|a1 a2;a3 a4')]]);
    });
});
