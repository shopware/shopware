import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-cms/component/sw-cms-block';
import 'src/module/sw-cms/component/sw-cms-visibility-toggle';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-cms-block'), {
        localVue,
        propsData: {
            block: {
                visibility: {
                    mobile: true,
                    tablet: true,
                    desktop: true,
                }
            }
        },
        provide: {
            cmsService: {}
        },
        stubs: {
            'sw-icon': true,
            'sw-cms-visibility-toggle': Shopware.Component.build('sw-cms-visibility-toggle'),
        }
    });
}
describe('module/sw-cms/component/sw-cms-block', () => {
    beforeAll(() => {
        Shopware.State.registerModule('cmsPageState', {
            namespaced: true,
            state: {
                currentCmsDeviceView: 'desktop',
            },
        });
    });

    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('the overlay should exist and be visible', async () => {
        const wrapper = createWrapper();

        const overlay = wrapper.find('.sw-cms-block__config-overlay');
        expect(overlay.exists()).toBeTruthy();
        expect(overlay.isVisible()).toBeTruthy();
    });

    it('the overlay should not exist', async () => {
        const wrapper = createWrapper();
        await wrapper.setProps({
            disabled: true
        });

        const overlay = wrapper.find('.sw-cms-block__config-overlay');
        expect(overlay.exists()).toBeFalsy();
    });

    it('the visibility toggle wrapper should exist and be visible', async () => {
        const wrapper = createWrapper();

        await wrapper.setData({
            block: {
                visibility: {
                    mobile: true,
                    tablet: true,
                    desktop: false,
                }
            }
        });

        expect(wrapper.find('.sw-cms-visibility-toggle-wrapper').exists()).toBeTruthy();
    });

    it('the visibility toggle wrapper should not exist', async () => {
        const wrapper = createWrapper();

        expect(wrapper.find('.sw-cms-visibility-toggle-wrapper').exists()).toBeFalsy();
    });
});
