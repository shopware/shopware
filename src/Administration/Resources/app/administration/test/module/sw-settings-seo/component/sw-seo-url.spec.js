import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-settings-seo/component/sw-seo-url';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-seo-url'), {
        localVue,
        stubs: {
            'sw-card': '<div><slot name="toolbar"></slot></div>',
            'sw-sales-channel-switch': true
        },
        mocks: {
            $tc: v => v,
            $store: Shopware.State._store
        },
        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => Promise.resolve([]),
                    create: () => ({}),
                    schema: {
                        entity: {}
                    }
                })
            }
        }
    });
}

describe('src/module/sw-settings-seo/component/sw-seo-url', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
        Shopware.State.commit('swSeoUrl/setCurrentSeoUrl', '');
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('sales channel switch should not be disabled', () => {
        wrapper.vm.showEmptySeoUrlError = false;

        const salesChannelSwitch = wrapper.find('sw-sales-channel-switch-stub');
        expect(salesChannelSwitch.attributes().disabled).toBeUndefined();
    });

    it('sales channel switch should not be disabled', () => {
        wrapper.vm.showEmptySeoUrlError = false;
        wrapper.setProps({
            disabled: true
        });

        const salesChannelSwitch = wrapper.find('sw-sales-channel-switch-stub');
        expect(salesChannelSwitch.attributes().disabled).toBe('true');
    });
});
