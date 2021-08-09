import { shallowMount, createLocalVue, config } from '@vue/test-utils';
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
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import Vuex from 'vuex';

describe('src/module/sw-product/view/sw-product-detail-context-prices', () => {
    Shopware.State.registerModule('swProductDetail', productStore);
    Shopware.State.registerModule('context', {
        namespaced: true,

        getters: {
            isSystemDefaultLanguage() {
                return true;
            }
        }
    });

    const createWrapper = () => {
        // delete global $router and $routes mocks
        delete config.mocks.$router;
        delete config.mocks.$route;

        const localVue = createLocalVue();

        localVue.use(VueRouter);
        localVue.use(Vuex);
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
                'sw-field-error': true,
                'sw-button': true,
                'sw-data-grid': Shopware.Component.build('sw-data-grid'),
                'sw-data-grid-settings': true,
                'sw-field': Shopware.Component.build('sw-field'),
                'sw-number-field': Shopware.Component.build('sw-number-field'),
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-context-button': true,
                'sw-context-menu-item': true
            },
            provide: {
                repositoryFactory: {
                    create: (repositoryName) => {
                        if (repositoryName === 'rule') {
                            const rules = [
                                {
                                    id: 'ruleId',
                                    name: 'ruleName'
                                }
                            ];
                            rules.total = rules.length;

                            return {
                                search: () => Promise.resolve(rules)
                            };
                        }

                        return {};
                    }
                },
                acl: { can: () => true },
                validationService: {}
            }
        });
    };

    /** @type Wrapper */
    let wrapper;

    afterEach(async () => {
        Shopware.State.commit('swProductDetail/setProduct', {});
        Shopware.State.commit('swProductDetail/setParentProduct', {});

        if (wrapper) await wrapper.destroy();
    });

    it('should be able to instantiate', async () => {
        wrapper = createWrapper();
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

        wrapper = createWrapper();
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

        wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.isChild).toBeFalsy();
        expect(wrapper.vm.isInherited).toBeFalsy();
    });
});
