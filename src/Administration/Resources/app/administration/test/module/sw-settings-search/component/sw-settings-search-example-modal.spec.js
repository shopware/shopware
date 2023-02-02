import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-search/component/sw-settings-search-example-modal';
import 'src/app/component/base/sw-modal';

function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-settings-search-example-modal'), {
        localVue,

        provide: {
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {}
            },
            acl: {
                can: () => true
            }
        },

        stubs: {
            'sw-modal': Shopware.Component.build('sw-modal'),
            'sw-button': true,
            'sw-icon': true
        }
    });
}

describe('module/sw-settings-search/component/sw-settings-search-example-modal', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should emit modal close event', async () => {
        const wrapper = createWrapper();

        wrapper.vm.closeModal();

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('should show correct title', async () => {
        const wrapper = createWrapper();
        const title = await wrapper.find('.sw-settings-search-example-modal .sw-modal__title');

        expect(title.text()).toEqual(
            'sw-settings-search.generalTab.titleExampleModal'
        );
    });
});
