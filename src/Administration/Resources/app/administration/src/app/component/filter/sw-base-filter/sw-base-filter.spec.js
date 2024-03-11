import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-base-filter', { sync: true }), {
        props: {
            title: 'Example Filter',
            showResetButton: false,
            active: true,
        },
    });
}

describe('components/sw-base-filter', () => {
    it('should hide reset button by default', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-base-filter__reset').exists()).toBeFalsy();
    });

    it('should show reset button when showResetButton is true', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ showResetButton: true });

        expect(wrapper.find('.sw-base-filter__reset').exists()).toBeTruthy();

        await wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should show reset button when showResetButton is false', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ showResetButton: false });

        expect(wrapper.find('.sw-base-filter__reset').exists()).toBeFalsy();
    });

    it('should emit `filter-reset` when filter is not active', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ active: false });

        expect(wrapper.emitted()['filter-reset']).toBeTruthy();
    });

    it('should not emit `filter-reset` when filter is active', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({ active: true });

        expect(wrapper.emitted()['filter-reset']).toBeFalsy();
    });
});
