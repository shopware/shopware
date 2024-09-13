/**
 * @package services-settings
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-search-example-modal', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
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
                'sw-modal': await wrapTestComponent('sw-modal'),
                'sw-button': true,
                'sw-icon': true,
                'sw-loader': true,
            },
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
