/*
 * @package inventory
 */

import { mount } from '@vue/test-utils';
import uuid from 'src/../test/_helper_/uuid';

const { State } = Shopware;

const classes = {
    cardSeoAdditional: 'sw-seo-url__card-seo-additional',
    inheritanceWrapper: 'sw-inherit-wrapper',
    inheritanceSwitch: 'sw-inheritance-switch',
    notInherited: 'sw-inheritance-switch--is-not-inherited',
    inherited: 'sw-inheritance-switch--is-inherited',
};

const storefrontId = uuid.get('storefront');

const productNotInheritedCategoryDataMock = {
    product: {
        seoUrls: [
            {
                apiAlias: null,
                routeName: 'frontend.detail.page',
                salesChannelId: storefrontId,
            },
        ],
        mainCategories: [{
            _isNew: true,
            category: {},
            categoryId: uuid.get('category A'),
            extensions: {},
            salesChannelId: storefrontId,
        }],
        categories: [{ id: uuid.get('category A') }],
    },
    parentProduct: {
        id: uuid.get('parentProduct'),
        categories: [{ id: uuid.get('category B') }],
    },
    currentSalesChannelId: storefrontId,
};

const productInheritedCategoryDataMock = {
    ...productNotInheritedCategoryDataMock,
    product: {
        ...productNotInheritedCategoryDataMock.product,
        categories: [],
    },
};

const salesChannelRepositoryMock = {
    search: () => {
        return Promise.resolve(createEntityCollection([
            {
                name: 'Storefront',
                translated: { name: 'Storefront' },
                id: storefrontId,
            },
            {
                name: 'Headless',
                translated: { name: 'Headless' },
                id: uuid.get('headless'),
            },
        ]));
    },
};

const seoUrlRepositoryMock = {
    create: () => ({}),
    search: () => Promise.resolve([]),
    route: '/seo_url',
    schema: {
        entity: 'seo_url',
    },
};

const repositoryMockFactory = (entity) => {
    if (entity === 'sales_channel') {
        return salesChannelRepositoryMock;
    }

    if (entity === 'seo_url') {
        return seoUrlRepositoryMock;
    }

    return false;
};

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-product-detail-seo', { sync: true }), {
        global: {
            provide: {
                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },
                repositoryFactory: {
                    create: (entity) => repositoryMockFactory(entity),
                },
                validationService: {},
            },
            stubs: {
                'sw-card': {
                    data() {
                        return { currentSalesChannelId: null };
                    },
                    template: '<div>' +
                        '<slot name="toolbar"></slot>' +
                        '<slot></slot>' +
                        '</div>',
                },
                'sw-product-seo-form': await wrapTestComponent('sw-product-seo-form', { sync: true }),
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-seo-url': await wrapTestComponent('sw-seo-url', { sync: true }),
                'sw-seo-main-category': await wrapTestComponent('sw-seo-main-category', { sync: true }),
                'sw-sales-channel-switch': await wrapTestComponent('sw-sales-channel-switch', { sync: true }),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
                'sw-text-field': await wrapTestComponent('sw-text-field', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                'sw-select-base': await wrapTestComponent('sw-select-base', { sync: true }),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch', { sync: true }),
                'sw-icon': await wrapTestComponent('sw-icon', { sync: true }),
                'sw-icon-deprecated': await wrapTestComponent('sw-icon-deprecated', { sync: true }),
                'sw-help-text': true,
                'sw-loader': true,
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-skeleton': true,
            },
        },
    });
}

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

describe('src/module/sw-product/view/sw-product-detail-seo', () => {
    beforeEach(() => {
        if (Shopware.State.get('swProductDetail')) {
            Shopware.State.unregisterModule('swProductDetail');
        }

        State.registerModule('swProductDetail', {
            namespaced: true,
            state: {
                product: {},
                parentProduct: {},
            },
            getters: {
                isLoading: () => false,
            },
            mutations: {
                setProduct(state, newProduct) {
                    state.product = newProduct;
                },
                setParentProduct(state, newProduct) {
                    state.parentProduct = newProduct;
                },
            },
        });
    });

    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should update product main categories correctly', async () => {
        Shopware.State.commit('swProductDetail/setProduct', {
            mainCategories: [],
        });
        const wrapper = await createWrapper();
        await wrapper.vm.onAddMainCategory({
            _isNew: true,
            category: {},
            categoryId: '9e3bd98cd39e451ba477fc306e28af7d',
            extensions: {},
            salesChannelId: '6eaf45a9682d43e59dd4deb8bd116de0',
        });

        expect(wrapper.vm.product.mainCategories).toEqual(expect.arrayContaining([{
            _isNew: true,
            category: {},
            categoryId: '9e3bd98cd39e451ba477fc306e28af7d',
            extensions: {},
            salesChannelId: '6eaf45a9682d43e59dd4deb8bd116de0',
        }]));
    });

    it('should update main category when restore inheritance of Seo Category from variant', async () => {
        const wrapper = await createWrapper(['product.editor']);
        Shopware.State.commit('swProductDetail/setProduct', {
            ...productInheritedCategoryDataMock.product,
        });

        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: '123',
            mainCategories: productNotInheritedCategoryDataMock.product.mainCategories,
        });
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.product.mainCategories).toHaveLength(1);

        const salesChannelSwitch = wrapper.find('.sw-field[label="sw-seo-url.labelSalesChannelSelect"]');
        let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');
        await salesChannelSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();
        await flushPromises();

        const selectStoreFront = salesChannelSwitch.find('.sw-select-option--1');
        expect(selectStoreFront.text()).toBe('Storefront');
        await selectStoreFront.trigger('click');
        await flushPromises();

        selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('Storefront');
        expect(wrapper.vm.currentSalesChannelId).toEqual(storefrontId);

        const inheritanceSwitch = wrapper.find(`.${classes.cardSeoAdditional} .${classes.inheritanceSwitch}`);
        expect(inheritanceSwitch).toBeTruthy();

        expect(inheritanceSwitch.classes()).toContain(classes.notInherited);
        Shopware.State.commit('swProductDetail/setProduct', {
            mainCategories: [
                {
                    _isNew: true,
                    category: {},
                    categoryId: uuid.get('category A'),
                    extensions: {},
                    salesChannelId: storefrontId,
                },
            ],
            seoUrls: [
                {
                    apiAlias: null,
                    routeName: 'frontend.detail.page',
                    salesChannelId: storefrontId,
                },
            ],
            categories: [],
        });
        await flushPromises();
        await inheritanceSwitch.find('.sw-icon').trigger('click');

        expect(inheritanceSwitch.classes()).toContain(classes.inherited);

        expect(wrapper.vm.product.mainCategories).toHaveLength(0);
    });

    it('should not exist inheritance symbol when variant\'s category did not inherit parent\s category', async () => {
        const wrapper = await createWrapper('product.editor');

        Shopware.State.commit('swProductDetail/setProduct', {
            seoUrls: [
                {
                    apiAlias: null,
                    routeName: 'frontend.detail.page',
                    salesChannelId: storefrontId,
                },
            ],
            mainCategories: [{
                _isNew: true,
                category: {},
                categoryId: uuid.get('category A'),
                extensions: {},
                salesChannelId: storefrontId,
            }],
            categories: [{ id: uuid.get('category A') }],
        });
        await flushPromises();

        expect(wrapper.vm.product.categories).toHaveLength(1);
        expect(wrapper.vm.categories).toEqual(expect.arrayContaining(wrapper.vm.product.categories));

        const salesChannelSwitch = wrapper.find('.sw-field[label="sw-seo-url.labelSalesChannelSelect"]');
        let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');
        await salesChannelSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();
        await flushPromises();

        const selectStoreFront = salesChannelSwitch.find('.sw-select-option--1');
        expect(selectStoreFront.text()).toBe('Storefront');
        await selectStoreFront.trigger('click');
        await wrapper.vm.$nextTick();

        selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('Storefront');
        expect(wrapper.vm.currentSalesChannelId).toEqual(storefrontId);


        const inheritanceSwitch = wrapper.find(`.${classes.cardSeoAdditional} .${classes.inheritanceSwitch}`);
        expect(inheritanceSwitch.exists()).toBe(false);
    });

    it('should exist inheritance symbol when variant\'s Seo Category does not have main category', async () => {
        const wrapper = await createWrapper('product.editor');

        Shopware.State.commit('swProductDetail/setProduct', {
            seoUrls: [
                {
                    apiAlias: null,
                    routeName: 'frontend.detail.page',
                    salesChannelId: storefrontId,
                },
            ],
            mainCategories: [{
                _isNew: true,
                category: {},
                categoryId: uuid.get('category A'),
                extensions: {},
                salesChannelId: storefrontId,
            }],
            categories: [{ id: uuid.get('category A') }],
        });

        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: '123',
            categories: [{ id: uuid.get('category A') }],
        });
        await flushPromises();

        expect(wrapper.vm.categories).toEqual(expect.arrayContaining(wrapper.vm.parentProduct.categories));

        const salesChannelSwitch = wrapper.find('.sw-field[label="sw-seo-url.labelSalesChannelSelect"]');
        let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');
        await salesChannelSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();
        await flushPromises();

        const selectHeadless = salesChannelSwitch.find('.sw-select-option--2');
        expect(selectHeadless.text()).toBe('Headless');
        await selectHeadless.trigger('click');
        await wrapper.vm.$nextTick();
        await flushPromises();

        selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('Headless');
        expect(wrapper.vm.currentSalesChannelId).toEqual(uuid.get('headless'));

        Shopware.State.commit('swProductDetail/setProduct', {
            mainCategories: [
                {
                    _isNew: true,
                    category: {},
                    categoryId: uuid.get('category A'),
                    extensions: {},
                    salesChannelId: storefrontId,
                },
            ],
            seoUrls: [
                {
                    apiAlias: null,
                    routeName: 'frontend.detail.page',
                    salesChannelId: storefrontId,
                },
            ],
            categories: [],
        });

        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: '123',
            mainCategories: productNotInheritedCategoryDataMock.product.mainCategories,
        });
        await flushPromises();
        const inheritanceSwitch = wrapper.find(`.${classes.cardSeoAdditional} .${classes.inheritanceSwitch}`);
        expect(inheritanceSwitch).toBeTruthy();

        expect(inheritanceSwitch.classes()).toContain(classes.inherited);
    });

    it('should exist non-inheritance symbol when variant\'s Seo Category have main category', async () => {
        const wrapper = await createWrapper(['product.editor']);

        Shopware.State.commit('swProductDetail/setProduct', {
            seoUrls: [
                {
                    apiAlias: null,
                    routeName: 'frontend.detail.page',
                    salesChannelId: storefrontId,
                },
            ],
            mainCategories: [{
                _isNew: true,
                category: {},
                categoryId: uuid.get('category A'),
                extensions: {},
                salesChannelId: storefrontId,
            }],
            categories: [],
        });

        Shopware.State.commit('swProductDetail/setParentProduct', {
            id: '123',
            categories: [{ id: uuid.get('category A') }],
        });
        await flushPromises();

        expect(wrapper.vm.product.mainCategories).toHaveLength(1);
        expect(wrapper.vm.categories).toEqual(expect.arrayContaining(wrapper.vm.parentProduct.categories));

        const salesChannelSwitch = wrapper.find('.sw-field[label="sw-seo-url.labelSalesChannelSelect"]');
        let selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('sw-sales-channel-switch.labelDefaultOption');
        await salesChannelSwitch.find('.sw-select__selection').trigger('click');
        await wrapper.vm.$nextTick();
        await flushPromises();

        const selectStoreFront = salesChannelSwitch.find('.sw-select-option--1');
        expect(selectStoreFront.text()).toBe('Storefront');
        await selectStoreFront.trigger('click');
        await wrapper.vm.$nextTick();
        await flushPromises();

        selectionText = salesChannelSwitch.find('.sw-entity-single-select__selection-text');
        expect(selectionText.text()).toBe('Storefront');
        expect(wrapper.vm.currentSalesChannelId).toEqual(storefrontId);

        const inheritanceSwitch = wrapper.find(`.${classes.cardSeoAdditional} .${classes.inheritanceSwitch}`);
        expect(inheritanceSwitch).toBeTruthy();

        expect(inheritanceSwitch.classes()).toContain(classes.notInherited);
    });
});
