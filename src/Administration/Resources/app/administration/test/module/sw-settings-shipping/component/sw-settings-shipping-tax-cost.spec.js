import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-settings-shipping/component/sw-settings-shipping-tax-cost';
import state from 'src/module/sw-settings-shipping/page/sw-settings-shipping-detail/state';

Shopware.State.registerModule('swShippingDetail', state);

const createWrapper = () => {
    const localVue = createLocalVue();

    return shallowMount(Shopware.Component.build('sw-settings-shipping-tax-cost'), {
        localVue,
        store: Shopware.State._store,
        stubs: {
            'sw-card': true,
            'sw-single-select': true,
            'sw-entity-single-select': true
        },
        provide: {
            repositoryFactory: {
                create: (name) => {
                    if (name === 'tax') {
                        return {
                            search: () => Promise.resolve([]),
                            get: () => Promise.resolve({})
                        };
                    }

                    return null;
                }
            }

        }
    });
};

describe('module/sw-settings-shipping/component/sw-settings-shipping-tax-cost', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();

        wrapper.destroy();
    });

    it('should have a tax cost selection field', async () => {
        const wrapper = createWrapper();

        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            id: '1123123123',
            taxType: 'fixed'
        });

        await wrapper.vm.$nextTick();
        const taxIdSelection = wrapper.findComponent({ ref: 'taxIdSelection' });
        expect(taxIdSelection.exists()).toBe(true);

        wrapper.destroy();
    });

    it('should not have a tax cost selection field', async () => {
        const wrapper = createWrapper();

        let taxIdSelection;
        // Auto
        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            id: '1123123123',
            taxType: 'auto'
        });

        await wrapper.vm.$nextTick();
        taxIdSelection = wrapper.findComponent({ ref: 'taxIdSelection' });
        expect(taxIdSelection.exists()).toBeFalsy();

        // Highest
        Shopware.State.commit('swShippingDetail/setShippingMethod', {
            id: '1123123123',
            taxType: 'highest'
        });

        await wrapper.vm.$nextTick();
        taxIdSelection = wrapper.findComponent({ ref: 'taxIdSelection' });
        expect(taxIdSelection.exists()).toBeFalsy();

        wrapper.destroy();
    });
});
