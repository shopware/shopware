import { mount } from '@vue/test-utils';

const createWrapper = async (customOptions) => {
    return mount(await wrapTestComponent('sw-media-display-options', { sync: true }), {
        global: {
            stubs: {},
        },
        ...customOptions,
    });
};

describe('src/module/sw-media/component/sw-media-display-options', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should return the correct presentationOptions', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // Click on the presentation dropdown
        const presentationSelect = wrapper.find('.sw-media-display-options__label-presentation');
        await presentationSelect.find('.mt-select__selection').trigger('click');

        // Contains 4 preview options
        const selectResults = wrapper.findAll('.mt-select-result');
        expect(selectResults).toHaveLength(4);
        expect(selectResults[0].text()).toBe('sw-media.presentation.labelPresentationSmall');
        expect(selectResults[1].text()).toBe('sw-media.presentation.labelPresentationMedium');
        expect(selectResults[2].text()).toBe('sw-media.presentation.labelPresentationLarge');
        expect(selectResults[3].text()).toBe('sw-media.presentation.labelPresentationList');
    });
});
