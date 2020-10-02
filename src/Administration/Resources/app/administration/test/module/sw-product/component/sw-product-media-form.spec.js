import { createLocalVue, shallowMount } from '@vue/test-utils';
import Vuex from 'vuex';
import 'src/module/sw-product/component/sw-product-media-form';

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);
    localVue.directive('draggable', {});
    localVue.directive('droppable', {});

    return shallowMount(Shopware.Component.build('sw-product-media-form'), {
        localVue,
        mocks: {
            $tc: () => {},
            $store: new Vuex.Store({
                modules: {
                    swProductDetail: {
                        namespaced: true,
                        getters: {
                            isLoading: () => false
                        }
                    }
                }
            })
        },
        provide: {
            repositoryFactory: {},
            acl: {
                can: (identifier) => {
                    if (!identifier) { return true; }

                    return privileges.includes(identifier);
                }
            }
        },
        stubs: {
            'sw-upload-listener': true,
            'sw-product-image': true,
            'sw-media-upload-v2': true
        }
    });
}

describe('module/sw-product/component/sw-product-media-form', () => {
    beforeAll(() => {
        const product = {
            media: []
        };
        product.getEntityName = () => 'T-Shirt';

        Shopware.State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: product
            }
        });
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the sw-media-upload-v2 component', async () => {
        const wrapper = createWrapper([
            'product.editor'
        ]);

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeTruthy();
    });

    it('should not show the sw-media-upload-v2 component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.find('sw-media-upload-v2-stub').exists()).toBeFalsy();
    });
});
