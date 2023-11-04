/**
 * @package system-settings
 */
import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsSearchExampleModal from 'src/module/sw-settings-search/component/sw-settings-search-example-modal';
import 'src/app/component/base/sw-modal';

Shopware.Component.register('sw-settings-search-example-modal', swSettingsSearchExampleModal);

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-settings-search-example-modal'), {
        localVue,

        provide: {
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {},
            },
            acl: {
                can: () => true,
            },
        },

        stubs: {
            'sw-modal': await Shopware.Component.build('sw-modal'),
            'sw-button': true,
            'sw-icon': true,
        },
    });
}

describe('module/sw-settings-search/component/sw-settings-search-example-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should emit modal close event', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.closeModal();

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should show correct title', async () => {
        const wrapper = await createWrapper();
        const title = await wrapper.find('.sw-settings-search-example-modal .sw-modal__title');

        expect(title.text()).toBe(
            'sw-settings-search.generalTab.titleExampleModal',
        );
    });
});
