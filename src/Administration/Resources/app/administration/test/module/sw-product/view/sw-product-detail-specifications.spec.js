import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/view/sw-product-detail-specifications';

const { Component, State } = Shopware;

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Component.build('sw-product-detail-specifications'), {
        localVue,
        mocks: {
            $t: key => key,
            $tc: key => key,
            $store: State._store
        },
        provide: {
            feature: {
                isActive: () => true
            },
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            }
        },
        stubs: {
            'sw-card': true,
            'sw-product-packaging-form': true,
            'sw-product-detail-properties': true
        }
    });
}

describe('src/module/sw-product/view/sw-product-detail-specifications', () => {
    beforeAll(() => {
        State.registerModule('swProductDetail', {
            namespaced: true,
            getters: {
                isLoading: () => false
            }
        });
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });
});
