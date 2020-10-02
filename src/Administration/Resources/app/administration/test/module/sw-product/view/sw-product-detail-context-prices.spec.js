import { shallowMount, createLocalVue } from '@vue/test-utils';
import VueRouter from 'vue-router';
import productStore from 'src/module/sw-product/page/sw-product-detail/state';
import 'src/module/sw-product/view/sw-product-detail-context-prices';
import 'src/app/component/base/sw-container';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import Vuex from 'vuex';


describe('src/module/sw-product/view/sw-product-detail-context-prices', () => {
    Shopware.State.registerModule('swProductDetail', productStore);

    const createWrapper = () => {
        const localVue = createLocalVue();

        localVue.use(VueRouter);
        localVue.use(Vuex);
        localVue.directive('tooltip', {});
        localVue.filter('asset', key => key);

        return shallowMount(Shopware.Component.build('sw-product-detail-context-prices'), {
            localVue,
            stubs: {
                'sw-container': Shopware.Component.build('sw-container'),
                'sw-card': true,
                'sw-icon': true,
                'sw-loader': true,
                'sw-switch-field': Shopware.Component.build('sw-switch-field'),
                'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
                'sw-inheritance-switch': true,
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': true
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                $device: {
                    onResize: () => {}
                },
                $store: Shopware.State._store
            },
            provide: {
                repositoryFactory: {
                    create: (repositoryName) => {
                        if (repositoryName === 'rule') {
                            return {
                                search: () => Promise.resolve({
                                    0: { id: 'ruleId', name: 'ruleName' },
                                    total: 1
                                })
                            };
                        }

                        return {};
                    }
                },
                acl: { can: () => true }
            }
        });
    };

    it('should be able to instantiate', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show inherited state when product is a variant', async () => {
        Shopware.State.commit('swProductDetail/setProduct', {
            id: 'productId',
            parentId: 'parentProductId',
            prices: []
        });
        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: 'parentProductId'
        });

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBeTruthy();
        expect(wrapper.vm.isInherited).toBeTruthy();
    });

    it('should show empty state for main product', async () => {
        Shopware.State.commit('swProductDetail/setProduct', {
            id: 'productId',
            parentId: null,
            prices: []
        });
        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: 'parentProductId'
        });

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBeFalsy();
        expect(wrapper.vm.isInherited).toBeFalsy();
    });
});
