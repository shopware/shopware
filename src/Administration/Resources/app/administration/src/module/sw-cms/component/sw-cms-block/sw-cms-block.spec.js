
/**
 * @package content
 */
import { shallowMount } from '@vue/test-utils';
import swCmsBlock from 'src/module/sw-cms/component/sw-cms-block';
import 'src/module/sw-cms/component/sw-cms-visibility-toggle';

Shopware.Component.register('sw-cms-block', swCmsBlock);

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-cms-block'), {
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
            'sw-cms-visibility-toggle': await Shopware.Component.build('sw-cms-visibility-toggle'),
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
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('the overlay should exist and be visible', async () => {
        const wrapper = await createWrapper();

        const overlay = wrapper.find('.sw-cms-block__config-overlay');
        expect(overlay.exists()).toBeTruthy();
        expect(overlay.isVisible()).toBeTruthy();
    });

    it('the overlay should not exist', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true
        });

        const overlay = wrapper.find('.sw-cms-block__config-overlay');
        expect(overlay.exists()).toBeFalsy();
    });

    it('the visibility toggle wrapper should exist and be visible', async () => {
        const wrapper = await createWrapper();

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
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-cms-visibility-toggle-wrapper').exists()).toBeFalsy();
    });
});
