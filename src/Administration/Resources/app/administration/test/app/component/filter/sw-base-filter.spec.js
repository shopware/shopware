import 'src/app/component/filter/sw-base-filter';
import { createLocalVue, shallowMount } from '@vue/test-utils';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-base-filter'), {
        localVue,
        propsData: {
            title: 'Example Filter'
        },
        mocks: {
            $tc: key => key
        }
    });
}

describe('components/sw-base-filter', () => {
    it('should hide reset button by default', async () => {
        const wrapper = createWrapper();

        expect(wrapper.find('.sw-base-filter__reset').exists()).toBeFalsy();
    });

    it('should show reset button when showResetButton is true', async () => {
        const wrapper = createWrapper();

        await wrapper.setProps({ showResetButton: true });

        expect(wrapper.find('.sw-base-filter__reset').exists()).toBeTruthy();

        wrapper.find('.sw-base-filter__reset').trigger('click');

        expect(wrapper.emitted().resetFilter).toBeTruthy();
    });
});
