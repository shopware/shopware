/**
 * @package system-settings
 */
import { shallowMount, createLocalVue } from '@vue/test-utils_v2';
import 'src/app/component/structure/sw-card-view';

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-card-view'), {
        localVue,
        stubs: {
            'sw-error-summary': true,
        },
        propsData: {
            showErrorSummary: true,
        },
    });
}

describe('src/app/component/structure/sw-card-view', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should be able to turn off the error summary component', async () => {
        expect(wrapper.find('sw-error-summary-stub').exists()).toBeTruthy();

        await wrapper.setProps({
            showErrorSummary: false,
        });

        expect(wrapper.find('sw-error-summary-stub').exists()).toBeFalsy();
    });
});
